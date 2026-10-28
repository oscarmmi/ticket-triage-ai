<?php

use App\Models\SupportTicket;
use App\Models\TriageRun;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmResponse;
use App\Services\Triage\TicketTriageService;
use Database\Seeders\DatabaseSeeder;

function supportTicket(array $overrides = []): SupportTicket
{
    return SupportTicket::create(array_merge([
        'subject' => 'A question from Harbor Desk customer',
        'body' => 'Could someone help me with this request?',
        'channel' => 'email',
        'customer_name' => 'Morgan Lee',
        'status' => 'open',
    ], $overrides));
}

test('invoice ticket is classified as high priority billing', function () {
    app()->instance(LlmClient::class, new FakeLlmClient);
    $ticket = supportTicket([
        'subject' => 'Question about my invoice',
        'body' => 'The invoice includes a charge I do not recognize.',
    ]);

    $result = app(TicketTriageService::class)->triage($ticket);

    expect($result->succeeded)->toBeTrue()
        ->and($ticket->fresh()->predicted_category)->toBe('billing')
        ->and($ticket->fresh()->predicted_priority)->toBe('high')
        ->and($ticket->fresh()->suggested_reply)->not->toBeEmpty()
        ->and($ticket->triageRuns()->count())->toBe(1);
});

test('crash ticket is classified as a product bug', function () {
    app()->instance(LlmClient::class, new FakeLlmClient);
    $ticket = supportTicket([
        'subject' => 'The mobile app crashes',
        'body' => 'The app crashes every time I open my saved report.',
    ]);

    $result = app(TicketTriageService::class)->triage($ticket);

    expect($result->succeeded)->toBeTrue()
        ->and($ticket->fresh()->predicted_category)->toBe('product_bug')
        ->and($ticket->fresh()->predicted_priority)->toBe('high');
});

test('chargebacks are urgent and hours questions are low priority', function () {
    $service = app(TicketTriageService::class);
    $chargeback = supportTicket([
        'subject' => 'I may file a chargeback',
        'body' => 'I am disputing this duplicate charge with my bank.',
    ]);
    $hours = supportTicket([
        'subject' => 'What are your hours?',
        'body' => 'Could you tell me your support hours this week?',
    ]);

    $chargebackResult = $service->triage($chargeback);
    $hoursResult = $service->triage($hours);

    expect($chargebackResult->succeeded)->toBeTrue()
        ->and($chargeback->fresh()->predicted_category)->toBe('billing')
        ->and($chargeback->fresh()->predicted_priority)->toBe('urgent')
        ->and($hoursResult->succeeded)->toBeTrue()
        ->and($hours->fresh()->predicted_category)->toBe('other')
        ->and($hours->fresh()->predicted_priority)->toBe('low');
});

test('the no-key provider is the rule-based fake', function () {
    config(['services.gemini.key' => null]);

    expect(app(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class);
});

test('classify endpoint returns a triaged suggestion with HTTP 200', function () {
    $ticket = supportTicket([
        'subject' => 'Invoice question',
        'body' => 'I see a charge that does not match my plan.',
    ]);

    $this->postJson(route('tickets.classify', $ticket))
        ->assertOk()
        ->assertJsonPath('succeeded', true)
        ->assertJsonPath('ticket.predicted_category', 'billing')
        ->assertJsonPath('ticket.predicted_priority', 'high');

    expect(TriageRun::where('support_ticket_id', $ticket->id)->count())->toBe(1);
});

test('invalid model JSON is recorded and leaves the ticket open without a server error', function () {
    app()->instance(LlmClient::class, new class implements LlmClient
    {
        public function complete(array $input, array $schema): LlmResponse
        {
            return new LlmResponse('{not-json', 'test-invalid');
        }
    });
    $ticket = supportTicket(['subject' => 'Broken model response']);

    $this->postJson(route('tickets.classify', $ticket))
        ->assertOk()
        ->assertJsonPath('succeeded', false)
        ->assertJsonPath('ticket.status', 'open')
        ->assertJsonPath('ticket.predicted_category', null);

    expect($ticket->fresh()->status)->toBe('open')
        ->and($ticket->fresh()->predicted_category)->toBeNull()
        ->and($ticket->triageRuns()->first()->raw_json)->toBe('{not-json');
});

test('a provider exception is returned as a recoverable triage error', function () {
    app()->instance(LlmClient::class, new class implements LlmClient
    {
        public function complete(array $input, array $schema): LlmResponse
        {
            throw new RuntimeException('Provider timeout');
        }
    });
    $ticket = supportTicket(['subject' => 'Provider timeout example']);

    $this->postJson(route('tickets.classify', $ticket))
        ->assertOk()
        ->assertJsonPath('succeeded', false)
        ->assertJsonPath('ticket.status', 'open')
        ->assertJsonPath('ticket.latest_model', 'provider-error');

    expect($ticket->fresh()->predicted_category)->toBeNull()
        ->and($ticket->triageRuns()->count())->toBe(1);
});

test('artisan triages open tickets that are missing a category', function () {
    $ticket = supportTicket([
        'subject' => 'Where is my package?',
        'body' => 'The tracking page has not updated since it shipped.',
    ]);
    $alreadyCategorized = supportTicket([
        'subject' => 'Already categorized',
        'body' => 'A reply is ready for review.',
        'predicted_category' => 'other',
        'predicted_priority' => 'low',
    ]);

    $this->artisan('tickets:triage')->assertSuccessful();

    expect($ticket->fresh()->predicted_category)->toBe('shipping')
        ->and($ticket->fresh()->status)->toBe('triaged')
        ->and($alreadyCategorized->fresh()->status)->toBe('open')
        ->and($alreadyCategorized->triageRuns()->count())->toBe(0);
});

test('accepting a draft saves the edited reply and marks the ticket triaged', function () {
    $ticket = supportTicket(['suggested_reply' => 'Original draft']);

    $this->patchJson(route('tickets.accept', $ticket), ['suggested_reply' => 'Edited by the agent.'])
        ->assertOk()
        ->assertJsonPath('ticket.suggested_reply', 'Edited by the agent.')
        ->assertJsonPath('ticket.status', 'triaged');
});

test('demo seeder creates twelve fictional tickets with three featured examples', function () {
    $this->seed(DatabaseSeeder::class);

    expect(SupportTicket::count())->toBe(12)
        ->and(SupportTicket::where('is_featured', true)->count())->toBe(3)
        ->and(SupportTicket::where('predicted_priority', 'urgent')->count())->toBe(1)
        ->and(SupportTicket::where('predicted_priority', 'low')->count())->toBe(1)
        ->and(SupportTicket::whereNotNull('predicted_category')->distinct()->pluck('predicted_category')->sort()->values()->all())
        ->toBe(['account', 'billing', 'other', 'product_bug', 'shipping']);
});

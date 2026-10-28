<?php

namespace App\Services\Triage;

use App\Models\SupportTicket;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

class TicketTriageService
{
    /** @var array<string, mixed> */
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'category' => ['type' => 'string', 'enum' => ['billing', 'shipping', 'product_bug', 'account', 'other']],
            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
            'suggested_reply' => ['type' => 'string'],
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'rationale' => ['type' => 'string'],
        ],
        'required' => ['category', 'priority', 'suggested_reply', 'confidence', 'rationale'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly LlmClient $llmClient) {}

    public function triage(SupportTicket $ticket): TriageResult
    {
        try {
            $response = $this->llmClient->complete([
                'subject' => $ticket->subject,
                'body' => $ticket->body,
                'channel' => $ticket->channel,
                'customer_name' => $ticket->customer_name,
            ], self::SCHEMA);
        } catch (Throwable $exception) {
            Log::warning('Ticket triage provider failed.', [
                'ticket_id' => $ticket->id,
                'exception' => $exception::class,
            ]);

            $this->recordRun($ticket, json_encode(['error' => 'The triage provider could not complete the request.'], JSON_THROW_ON_ERROR), 'provider-error');

            return new TriageResult(false, 'Triage is temporarily unavailable. Your ticket is still open. Please try again.');
        }

        $raw = $response->content;
        $this->recordRun($ticket, $raw, $response->modelName);

        try {
            $result = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new TriageResult(false, 'The triage response could not be validated. Your ticket is still open. Please try again.');
        }

        $validator = Validator::make(is_array($result) ? $result : [], [
            'category' => ['required', 'string', 'in:billing,shipping,product_bug,account,other'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'suggested_reply' => ['required', 'string', 'max:5000'],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'rationale' => ['required', 'string', 'max:1000'],
        ]);

        if (! is_array($result) || $validator->fails() || count($result) !== count(self::SCHEMA['required'])) {
            return new TriageResult(false, 'The triage response could not be validated. Your ticket is still open. Please try again.');
        }

        $validated = $validator->validated();
        $ticket->forceFill([
            'predicted_category' => $validated['category'],
            'predicted_priority' => $validated['priority'],
            'suggested_reply' => $validated['suggested_reply'],
            'confidence' => $validated['confidence'],
        ])->save();

        return new TriageResult(true);
    }

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return self::SCHEMA;
    }

    private function recordRun(SupportTicket $ticket, string $raw, string $model): void
    {
        $ticket->triageRuns()->create([
            'raw_json' => $raw,
            'model_name' => $model,
        ]);
    }
}

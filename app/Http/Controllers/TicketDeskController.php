<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\Triage\TicketTriageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketDeskController extends Controller
{
    public function index(): Response
    {
        $tickets = SupportTicket::query()
            ->with('latestTriageRun')
            ->orderByDesc('is_featured')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->get();

        return Inertia::render('Welcome', [
            'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->present($ticket)),
        ]);
    }

    public function classify(SupportTicket $ticket, TicketTriageService $triageService): JsonResponse
    {
        $result = $triageService->triage($ticket);

        return response()->json([
            'succeeded' => $result->succeeded,
            'error' => $result->error,
            'ticket' => $this->present($ticket->fresh()->load('latestTriageRun')),
        ]);
    }

    public function accept(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'suggested_reply' => ['required', 'string', 'max:5000'],
        ]);

        $ticket->forceFill([
            'suggested_reply' => $validated['suggested_reply'],
            'status' => 'triaged',
        ])->save();

        return response()->json(['ticket' => $this->present($ticket->fresh()->load('latestTriageRun'))]);
    }

    /** @return array<string, mixed> */
    private function present(SupportTicket $ticket): array
    {
        $run = $ticket->latestTriageRun;
        $decoded = $run ? json_decode($run->raw_json, true) : null;

        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'body' => $ticket->body,
            'channel' => $ticket->channel,
            'customer_name' => $ticket->customer_name,
            'predicted_category' => $ticket->predicted_category,
            'predicted_priority' => $ticket->predicted_priority,
            'suggested_reply' => $ticket->suggested_reply,
            'confidence' => $ticket->confidence,
            'status' => $ticket->status,
            'is_featured' => $ticket->is_featured,
            'created_at' => $ticket->created_at?->toISOString(),
            'rationale' => is_array($decoded) ? ($decoded['rationale'] ?? null) : null,
            'latest_model' => $run?->model_name,
        ];
    }
}

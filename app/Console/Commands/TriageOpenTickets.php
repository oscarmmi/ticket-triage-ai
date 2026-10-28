<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Services\Triage\TicketTriageService;
use Illuminate\Console\Command;

class TriageOpenTickets extends Command
{
    protected $signature = 'tickets:triage';

    protected $description = 'Classify open support tickets that do not have a category';

    public function handle(TicketTriageService $triageService): int
    {
        $tickets = SupportTicket::query()
            ->where('status', 'open')
            ->whereNull('predicted_category')
            ->get();

        $succeeded = 0;
        foreach ($tickets as $ticket) {
            $result = $triageService->triage($ticket);
            if ($result->succeeded) {
                $ticket->update(['status' => 'triaged']);
                $succeeded++;
            } else {
                $this->warn("Ticket {$ticket->id}: {$result->error}");
            }
        }

        $this->info("Triaged {$succeeded} of {$tickets->count()} open ticket(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use App\Ai\Summarizer;
use App\Models\Event;
use App\Models\Tenant;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Zusammenfassung einer Aufzeichnung in der Queue, im Mandanten des Termins. */
class SummarizeEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $tenantId, public int $eventId, public ?int $requestedBy = null) {}

    public function handle(CurrentTenant $current, Summarizer $summarizer, Notifier $notifier): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () use ($summarizer, $notifier) {
            $event = Event::find($this->eventId);
            if (! $event) {
                return;
            }
            $summary = $summarizer->event($event, $this->requestedBy);
            if ($this->requestedBy) {
                $notifier->send([$this->requestedBy], new Nachricht(
                    titel: $summary->isDone() ? 'Zusammenfassung fertig: '.$event->title : 'Zusammenfassung nicht möglich: '.$event->title,
                    text: $summary->isDone() ? count($summary->tasks ?? []).' Aufgabenvorschläge. Prüfe sie im Coach-Bereich beim Termin.' : (string) $summary->error,
                    url: route('termine.show', $event),
                    anlass: 'system',
                    tag: 'ki-'.$summary->id,
                ));
            }
        });
    }
}

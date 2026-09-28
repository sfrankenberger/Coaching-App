<?php

namespace App\Ai\Werkzeuge;

use App\Models\Event;
use App\Models\User;
use App\Support\Zeit;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

class TerminAnlegen extends Werkzeug
{
    public function __construct(protected CurrentTenant $current) {}

    public function name(): string
    {
        return 'termin_anlegen';
    }

    public function beschreibung(): string
    {
        return 'Einen 1:1-Termin mit einer Person eintragen (Ortszeit des Betriebs). Die Person bekommt Bescheid, der Zoom-Link kommt aus den Einstellungen.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str($this->wer() + [
            'start' => ['type' => 'string', 'description' => 'JJJJ-MM-TT HH:MM in Ortszeit'],
            'dauer' => ['type' => 'integer', 'description' => 'Minuten, Vorgabe 60'],
            'titel' => ['type' => 'string'],
        ], ['start']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);
        $tenant = $this->current->get();
        $start = Carbon::parse($args['start'], $tenant?->timezone ?: config('app.timezone'))->utc();
        abort_unless($start->isFuture(), 422, 'Der Termin liegt in der Vergangenheit.');
        $dauer = max(15, min(240, (int) ($args['dauer'] ?? 60)));
        $e = Event::create([
            'title' => filled($args['titel'] ?? null) ? trim($args['titel']) : (string) ($tenant?->setting('termine.einzel_titel') ?: 'Einzelsitzung'),
            'type' => 'one_on_one', 'user_id' => $m->user_id, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($dauer),
            'zoom_url' => $tenant?->setting('termine.einzel_zoom_url') ?: null,
            'location' => $tenant?->setting('termine.einzel_zoom_url') ? 'Online via Zoom' : null,
            'is_published' => true, 'settings' => ['eingetragen_von' => $von->id, 'quelle' => 'mcp'],
        ]);

        return ['ok' => true, 'event_id' => $e->id, 'wann' => Zeit::wann($e->starts_at), 'titel' => $e->title] + $this->kurz($m);
    }
}

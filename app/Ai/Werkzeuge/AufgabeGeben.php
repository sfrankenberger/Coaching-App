<?php

namespace App\Ai\Werkzeuge;

use App\Models\Task;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

class AufgabeGeben extends Werkzeug
{
    public function __construct(protected CurrentTenant $current) {}

    public function name(): string
    {
        return 'aufgabe_geben';
    }

    public function beschreibung(): string
    {
        return 'Einer Person eine Aufgabe geben (sichtbar für die Coachin, die Person bekommt Bescheid).';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str($this->wer() + [
            'titel' => ['type' => 'string'],
            'text' => ['type' => 'string'],
            'bis' => ['type' => 'string', 'description' => 'Datum JJJJ-MM-TT'],
        ], ['titel']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);
        abort_if(trim((string) ($args['titel'] ?? '')) === '', 422, 'Kein Titel.');
        $t = Task::create([
            'user_id' => $m->user_id, 'assigned_by' => $von->id, 'title' => trim($args['titel']),
            'body' => filled($args['text'] ?? null) ? trim($args['text']) : null,
            'due_at' => filled($args['bis'] ?? null) ? Carbon::parse($args['bis'], $this->current->get()?->timezone ?: config('app.timezone'))->utc() : null,
            'source' => 'coach', 'visibility' => 'coach',
        ]);

        return ['ok' => true, 'task_id' => $t->id, 'titel' => $t->title] + $this->kurz($m);
    }
}

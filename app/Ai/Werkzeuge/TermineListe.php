<?php

namespace App\Ai\Werkzeuge;

use App\Models\Event;
use App\Models\User;
use App\Support\Zeit;

class TermineListe extends Werkzeug
{
    public function name(): string
    {
        return 'termine';
    }

    public function beschreibung(): string
    {
        return 'Termine der nächsten Tage (Gruppencalls, 1:1, Reflexions- und Fragentage), auf Wunsch nur für eine Person.';
    }

    public function schema(): array
    {
        return $this->str($this->wer() + ['tage' => ['type' => 'integer', 'description' => 'Zeitraum ab heute, Vorgabe 14'], 'vergangene' => ['type' => 'boolean', 'description' => 'stattdessen die letzten Termine']]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $tage = max(1, min(180, (int) ($args['tage'] ?? 14)));
        $q = Event::query()->where('is_published', true)->with(['program:id,title', 'user:id,name']);
        if (! empty($args['membership_id']) || ! empty($args['person'])) {
            $m = $this->person($args);
            $q->where('user_id', $m->user_id);
        }
        $q = ($args['vergangene'] ?? false)
            ? $q->where('starts_at', '<', now())->where('starts_at', '>=', now()->subDays($tage))->orderByDesc('starts_at')
            : $q->where('starts_at', '>=', now()->subHours(2))->where('starts_at', '<', now()->addDays($tage))->orderBy('starts_at');

        return $q->limit(60)->get()->map(fn (Event $e) => [
            'event_id' => $e->id, 'titel' => $e->title, 'art' => $e->type, 'wann' => Zeit::wann($e->starts_at), 'start' => $e->starts_at->toIso8601String(),
            'mit' => $e->user?->name, 'kurs' => $e->program?->title, 'zoom' => $e->zoom_url, 'aufzeichnung' => (bool) $e->recording_url,
        ])->all();
    }
}

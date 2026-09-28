<?php

namespace App\Ai\Werkzeuge;

use App\Coach\Arbeitsliste;
use App\Models\User;
use App\Support\Zeit;

class HeuteListe extends Werkzeug
{
    public function __construct(protected Arbeitsliste $liste) {}

    public function name(): string
    {
        return 'heute';
    }

    public function beschreibung(): string
    {
        return 'Die Arbeitsliste der Coachin: wer auf Antwort wartet, was geteilt wurde, offene Fragen, Aufzeichnungen, die auf Freigabe warten, und die nächsten Termine.';
    }

    public function ausfuehren(array $args, User $von): array
    {
        $l = $this->liste->fuer();

        return [
            'wartet_auf_antwort' => $l['wartende']->map(fn ($z) => ['membership_id' => $z['membership']->id, 'name' => $z['user']->name, 'seit' => $z['wann'], 'text' => $z['text']])->values()->all(),
            'geteilt' => $l['geteilt']->map(fn ($z) => ['wer' => $z['wer'], 'was' => $z['was'], 'detail' => $z['detail'], 'wann' => Zeit::relativ($z['zeit'])])->all(),
            'fragen_ohne_antwort' => $l['fragen']->map(fn ($f) => ['id' => $f->id, 'titel' => $f->title, 'von' => $f->user?->name, 'kurs' => $f->program?->title])->all(),
            'wartet_auf_freigabe' => $l['freigaben']->map(fn ($e) => ['event_id' => $e->id, 'titel' => $e->title, 'wann' => Zeit::wann($e->starts_at)])->all(),
            'als_naechstes' => $l['termine']->map(fn ($e) => ['event_id' => $e->id, 'titel' => $e->title, 'wann' => Zeit::wann($e->starts_at), 'mit' => $e->user?->name ?? $e->program?->title ?? 'alle', 'zoom' => $e->zoom_url])->all(),
            'satz' => $l['ruhig'],
        ];
    }
}

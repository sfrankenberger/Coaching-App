<?php

namespace App\Ai\Werkzeuge;

use App\Coach\Lage;
use App\Models\User;
use App\Support\Zeit;

class LageAmpel extends Werkzeug
{
    public function __construct(protected Lage $lage) {}

    public function name(): string
    {
        return 'lage';
    }

    public function beschreibung(): string
    {
        return 'Die Ampel: wie alle begleiteten Personen gerade dastehen (rot wartet auf Antwort oder lange still, gelb Calls oder Aufgaben offen, grün alles im Fluss), dringendste zuerst.';
    }

    public function schema(): array
    {
        return $this->str(['nur_auffaellige' => ['type' => 'boolean', 'description' => 'nur rot und gelb (Vorgabe: ja)']]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $nur = $args['nur_auffaellige'] ?? true;

        return $this->lage->alle()->when($nur, fn ($c) => $c->where('stufe', '>', 1))->map(fn ($z) => [
            'membership_id' => $z['membership']->id, 'name' => $z['user']->name, 'ampel' => $z['farbe'], 'grund' => $z['grund'],
            'aufgaben' => $z['aufgaben'][0].' von '.$z['aufgaben'][1], 'calls_verpasst' => $z['verpasst'],
            'zuletzt_da' => $z['zuletzt'] ? Zeit::relativ($z['zuletzt']) : 'noch nie',
            'naechster_termin' => $z['naechster'] ? Zeit::wann($z['naechster']->starts_at) : null,
            'sitzungen' => $z['kontingent'] ? $z['kontingent']['offen'].' von '.$z['kontingent']['gesamt'].' offen' : null,
        ])->values()->all();
    }
}

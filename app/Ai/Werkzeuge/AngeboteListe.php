<?php

namespace App\Ai\Werkzeuge;

use App\Models\Offer;
use App\Models\User;

class AngeboteListe extends Werkzeug
{
    public function name(): string
    {
        return 'angebote';
    }

    public function beschreibung(): string
    {
        return 'Alle aktiven Angebote (was man kaufen kann) mit offer_id, Laufzeit und den Programmen, die sie freischalten.';
    }

    public function ausfuehren(array $args, User $von): array
    {
        return Offer::where('is_active', true)->with('programs:id,title,type')->orderBy('title')->get()
            ->map(fn (Offer $o) => ['offer_id' => $o->id, 'titel' => $o->title, 'gratis' => (bool) $o->is_free, 'laufzeit_tage' => $o->access_days, 'programme' => $o->programs->pluck('title')->all()])->all();
    }
}

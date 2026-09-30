<?php

namespace App\Ai\Werkzeuge;

use App\Models\Newsletter;
use App\Models\User;

class NewsletterListe extends Werkzeug
{
    public function name(): string
    {
        return 'newsletter_liste';
    }

    public function beschreibung(): string
    {
        return 'Die letzten Newsletter mit Stand und Zahlen (gesendet, geoeffnet, geklickt).';
    }

    public function ausfuehren(array $args, User $von): array
    {
        return ['newsletter' => Newsletter::latest()->limit(20)->get()->map(fn (Newsletter $n) => [
            'newsletter_id' => $n->id, 'betreff' => $n->betreff, 'status' => $n->status, 'tags' => $n->tags,
            'empfaenger' => $n->empfaenger, 'gesendet' => $n->gesendet, 'geoeffnet' => $n->geoeffnet, 'geklickt' => $n->geklickt,
            'gesendet_am' => $n->gesendet_at?->toDateTimeString(), 'geplant_am' => $n->geplant_at?->toDateTimeString(),
        ])->all()];
    }
}

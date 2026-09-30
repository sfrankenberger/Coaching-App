<?php

namespace App\Ai\Werkzeuge;

use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;

class NewsletterAnlegen extends Werkzeug
{
    public function name(): string
    {
        return 'newsletter_anlegen';
    }

    public function beschreibung(): string
    {
        return 'Newsletter als Entwurf anlegen: Betreff, Headline, Text ({vorname} wird ersetzt, [Text](https://...) fuer Links, Leerzeile = Absatz), Bild, Knopf, Tags der Empfaengerinnen (leer = alle bestaetigten Kontakte). Mit post_id werden Titel, Kurztext, Bild und Link aus einem Impuls uebernommen. Gesendet wird erst mit newsletter_senden.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'betreff' => ['type' => 'string'],
            'titel' => ['type' => 'string', 'description' => 'Headline in der Mail'],
            'text' => ['type' => 'string'],
            'vorschautext' => ['type' => 'string'],
            'bild_url' => ['type' => 'string'],
            'knopf_text' => ['type' => 'string'],
            'knopf_url' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'an Kontakte mit einem dieser Tags'],
            'post_id' => ['type' => 'integer', 'description' => 'Impuls, aus dem Titel, Text, Bild und Link kommen'],
            'geplant_am' => ['type' => 'string', 'description' => 'ISO-Zeit, dann geht er von selbst raus'],
        ]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $post = ! empty($args['post_id']) ? Post::find((int) $args['post_id']) : null;
        abort_if(! empty($args['post_id']) && ! $post, 404, 'Impuls nicht gefunden.');
        $betreff = trim((string) ($args['betreff'] ?? $post?->title ?? ''));
        $text = trim((string) ($args['text'] ?? ($post ? $post->excerptText(600) : '')));
        abort_if($betreff === '' || $text === '', 422, 'Betreff und Text (oder post_id) sind noetig.');
        $link = $args['knopf_url'] ?? ($post ? ($post->url ?: route('impulse.show', $post)) : null);
        $n = Newsletter::create([
            'created_by' => $von->id,
            'betreff' => $betreff,
            'titel' => $args['titel'] ?? $post?->title,
            'vorschautext' => $args['vorschautext'] ?? null,
            'text' => $text,
            'bild_url' => $args['bild_url'] ?? $post?->image_url,
            'knopf_text' => $args['knopf_text'] ?? ($link ? 'Weiterlesen' : null),
            'knopf_url' => $link,
            'tags' => array_values(array_unique(array_map([Kontakt::class, 'tagSauber'], (array) ($args['tags'] ?? [])))),
            'status' => filled($args['geplant_am'] ?? null) ? 'geplant' : 'entwurf',
            'geplant_at' => filled($args['geplant_am'] ?? null) ? Carbon::parse($args['geplant_am']) : null,
        ]);

        return ['newsletter_id' => $n->id, 'status' => $n->status, 'empfaenger' => $n->empfaengerQuery()->count(), 'tags' => $n->tags, 'bearbeiten' => url('/coach/newsletter/'.$n->id.'/bearbeiten')];
    }
}

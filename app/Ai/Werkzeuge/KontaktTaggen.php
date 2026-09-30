<?php

namespace App\Ai\Werkzeuge;

use App\Models\Kontakt;
use App\Models\User;
use App\Newsletter\Kontakte;

class KontaktTaggen extends Werkzeug
{
    public function __construct(protected Kontakte $kontakte) {}

    public function name(): string
    {
        return 'kontakt_taggen';
    }

    public function beschreibung(): string
    {
        return 'Einem Kontakt Tags geben oder nehmen. Gibt es die E-Mail noch nicht, wird der Kontakt als bestätigt angelegt (die Einwilligung liegt der Coachin vor), ohne Bestätigungsmail. Ein neuer Tag kann eine Serie auslösen.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'email' => ['type' => 'string', 'format' => 'email'],
            'name' => ['type' => 'string', 'description' => 'nur beim Anlegen noetig'],
            'tags_dazu' => ['type' => 'array', 'items' => ['type' => 'string']],
            'tags_weg' => ['type' => 'array', 'items' => ['type' => 'string']],
            'abmelden' => ['type' => 'boolean', 'description' => 'Kontakt abmelden (bekommt nichts mehr)'],
        ], ['email']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        abort_unless(filter_var($args['email'] ?? '', FILTER_VALIDATE_EMAIL), 422, 'Keine gültige E-Mail.');
        $k = Kontakt::where('email', mb_strtolower(trim($args['email'])))->first();
        $neu = ! $k;
        if ($neu) {
            abort_if(empty($args['tags_dazu']) && empty($args['name']), 422, 'Kontakt gibt es noch nicht: Name oder mindestens ein Tag angeben.');
            $k = $this->kontakte->anmelden($args['email'], $args['name'] ?? null, (array) ($args['tags_dazu'] ?? []), ['herkunft' => 'assistent', 'durch' => $von->name], false);
        } else {
            if (! empty($args['tags_dazu'])) {
                $this->kontakte->taggen($k, (array) $args['tags_dazu']);
            }
        }
        if (! empty($args['tags_weg'])) {
            $this->kontakte->enttaggen($k, (array) $args['tags_weg']);
        }
        if ($args['abmelden'] ?? false) {
            $this->kontakte->abmelden($k, 'assistent');
        }
        $k = $k->fresh();

        return ['neu' => $neu, 'id' => $k->id, 'email' => $k->email, 'name' => $k->name, 'status' => $k->status, 'tags' => $k->tags ?? []];
    }
}

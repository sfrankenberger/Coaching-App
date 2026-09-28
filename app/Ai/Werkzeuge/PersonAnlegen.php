<?php

namespace App\Ai\Werkzeuge;

use App\Models\CoachNote;
use App\Models\User;
use App\Shop\Zugang;

class PersonAnlegen extends Werkzeug
{
    public function __construct(protected Zugang $zugang) {}

    public function name(): string
    {
        return 'person_anlegen';
    }

    public function beschreibung(): string
    {
        return 'Neue Person anlegen (Kundin oder Teilnehmerin). Gibt es die E-Mail schon, wird die Person zurückgegeben statt doppelt angelegt. Auf Wunsch geht die Willkommensmail mit Anmeldelink raus.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'name' => ['type' => 'string', 'description' => 'Vor- und Nachname'],
            'email' => ['type' => 'string', 'format' => 'email'],
            'telefon' => ['type' => 'string'],
            'rolle' => ['type' => 'string', 'enum' => ['member', 'client'], 'description' => 'member = Teilnehmerin (Kurs), client = 1:1-Kundin'],
            'notiz' => ['type' => 'string', 'description' => 'private Notiz der Coachin, z. B. woher sie kommt'],
            'willkommensmail' => ['type' => 'boolean', 'description' => 'Mail mit Anmeldelink schicken (Vorgabe: nein)'],
        ], ['name', 'email']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        abort_unless(filter_var($args['email'] ?? '', FILTER_VALIDATE_EMAIL), 422, 'Keine gültige E-Mail.');
        [$user, $neu] = $this->zugang->ensureUser($args['email'], $args['name']);
        if (filled($args['telefon'] ?? null)) {
            $user->forceFill(['phone' => trim($args['telefon'])])->save();
        }
        $m = $user->membershipIn();
        if (($args['rolle'] ?? null) === 'client' && ! $m->role->canManage()) {
            $m->forceFill(['role' => 'client'])->save();
        }
        if (filled($args['notiz'] ?? null)) {
            CoachNote::create(['user_id' => $user->id, 'author_id' => $von->id, 'body' => trim($args['notiz'])]);
        }
        if ($args['willkommensmail'] ?? false) {
            $this->zugang->welcome($user);
        }

        return ['neu' => $neu, 'willkommensmail' => (bool) ($args['willkommensmail'] ?? false)] + $this->kurz($m->fresh('user'));
    }
}

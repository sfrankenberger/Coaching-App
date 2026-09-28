<?php

namespace App\Ai\Werkzeuge;

use App\Models\Membership;
use App\Models\User;

/**
 * Ein Werkzeug, das ein Assistent (Claude, ChatGPT ueber MCP, spaeter Leas eigener Assistent)
 * in der App ausfuehren darf. Jedes Werkzeug laeuft im Namen einer Person aus dem Team und
 * im Mandanten des Requests. Keine Ausnahme: alles, was Daten aendert, geht ueber die
 * bestehenden Dienste (Zugang, Chat, Terminvorschlag ...), nie direkt an der Datenbank vorbei.
 */
abstract class Werkzeug
{
    abstract public function name(): string;

    abstract public function beschreibung(): string;

    /** JSON-Schema der Eingabe (type object). */
    public function schema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass, 'additionalProperties' => false];
    }

    /** Aendert das Werkzeug etwas? (fuer die Anzeige beim Assistenten) */
    public function schreibt(): bool
    {
        return false;
    }

    /** Ergebnis als Array (wird als JSON-Text zurueckgegeben) oder als fertiger Text. */
    abstract public function ausfuehren(array $args, User $von): array|string;

    /** Person ueber Mitgliedschafts-ID, Mailadresse oder (eindeutigen) Namen finden. */
    protected function person(array $args): Membership
    {
        $q = Membership::query()->whereIn('status', ['active', 'paused'])->with('user');
        if (! empty($args['membership_id'])) {
            $m = (clone $q)->find((int) $args['membership_id']);
            abort_unless($m && $m->user, 404, 'Keine Person mit dieser membership_id.');

            return $m;
        }
        $such = trim((string) ($args['person'] ?? $args['email'] ?? $args['name'] ?? ''));
        abort_if($such === '', 422, 'Wer? Gib membership_id, E-Mail oder Namen an.');
        $treffer = (clone $q)->whereHas('user', fn ($u) => $u->where('email', mb_strtolower($such))->orWhere('name', 'like', '%'.$such.'%'))->get()->filter(fn ($m) => $m->user);
        abort_if($treffer->isEmpty(), 404, 'Niemand gefunden zu "'.$such.'".');
        abort_if($treffer->count() > 1, 409, 'Mehrere gefunden: '.$treffer->map(fn ($m) => $m->user->name.' (membership_id '.$m->id.')')->join(', ').'. Nimm die membership_id.');

        return $treffer->first();
    }

    protected function kurz(Membership $m): array
    {
        return [
            'membership_id' => $m->id,
            'name' => $m->user->name,
            'email' => $m->user->email,
            'telefon' => $m->user->phone,
            'rolle' => $m->role->label(),
            'dossier' => route('coachees.show', $m),
        ];
    }

    protected function str(array $props, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $props, 'additionalProperties' => false];
        if ($required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** Die drei ueblichen Felder, um eine Person zu benennen. */
    protected function wer(): array
    {
        return [
            'membership_id' => ['type' => 'integer', 'description' => 'ID der Mitgliedschaft (aus personen_suchen), am sichersten'],
            'person' => ['type' => 'string', 'description' => 'E-Mail oder Name, wenn eindeutig'],
        ];
    }
}

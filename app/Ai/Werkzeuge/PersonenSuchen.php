<?php

namespace App\Ai\Werkzeuge;

use App\Models\Membership;
use App\Models\User;
use App\Support\Zeit;

class PersonenSuchen extends Werkzeug
{
    public function name(): string
    {
        return 'personen_suchen';
    }

    public function beschreibung(): string
    {
        return 'Menschen im Betrieb suchen (Name oder E-Mail, leer = alle aktiven). Liefert membership_id, Name, E-Mail, Rolle, zuletzt da.';
    }

    public function schema(): array
    {
        return $this->str([
            'suche' => ['type' => 'string', 'description' => 'Teil des Namens oder der E-Mail, leer für alle'],
            'rolle' => ['type' => 'string', 'enum' => ['member', 'client', 'team', 'owner', 'guest'], 'description' => 'nur diese Rolle'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
        ]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $q = trim((string) ($args['suche'] ?? ''));
        $liste = Membership::query()->where('status', 'active')->with('user')
            ->when(! empty($args['rolle']), fn ($b) => $b->where('role', $args['rolle']))
            ->when($q !== '', fn ($b) => $b->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')))
            ->limit((int) ($args['limit'] ?? 50))->get()->filter(fn ($m) => $m->user);

        return ['anzahl' => $liste->count(), 'personen' => $liste->map(fn ($m) => $this->kurz($m) + ['zuletzt_da' => $m->last_seen_at ? Zeit::relativ($m->last_seen_at) : 'noch nie'])->values()->all()];
    }
}

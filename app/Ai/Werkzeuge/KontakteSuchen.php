<?php

namespace App\Ai\Werkzeuge;

use App\Models\Kontakt;
use App\Models\User;
use App\Newsletter\Kontakte;

class KontakteSuchen extends Werkzeug
{
    public function __construct(protected Kontakte $kontakte) {}

    public function name(): string
    {
        return 'kontakte_suchen';
    }

    public function beschreibung(): string
    {
        return 'Newsletter-Kontakte suchen (Name, E-Mail oder Tag) und alle Tags mit Anzahl sehen. Kontakte sind die Stufe unter Gast: Adressen mit Einwilligung, ohne Login.';
    }

    public function schema(): array
    {
        return $this->str([
            'suche' => ['type' => 'string', 'description' => 'Name oder E-Mail (Teil reicht)'],
            'tag' => ['type' => 'string', 'description' => 'nur Kontakte mit diesem Tag'],
            'status' => ['type' => 'string', 'enum' => ['bestaetigt', 'angemeldet', 'abgemeldet'], 'description' => 'Vorgabe: bestaetigt'],
            'limit' => ['type' => 'integer', 'description' => 'Vorgabe 20, hoechstens 100'],
        ]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $q = Kontakt::query()->where('status', $args['status'] ?? 'bestaetigt');
        if (filled($args['suche'] ?? null)) {
            $s = mb_strtolower(trim($args['suche']));
            $q->where(fn ($w) => $w->where('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
        }
        if (filled($args['tag'] ?? null)) {
            $q->mitTags([$args['tag']]);
        }
        $limit = min(100, max(1, (int) ($args['limit'] ?? 20)));
        $liste = $q->orderBy('name')->limit($limit)->get();

        return [
            'anzahl' => (clone $q)->count(),
            'kontakte' => $liste->map(fn (Kontakt $k) => ['id' => $k->id, 'name' => $k->name, 'email' => $k->email, 'status' => $k->status, 'tags' => $k->tags ?? [], 'seit' => $k->created_at?->toDateString()])->all(),
            'tags' => $this->kontakte->alleTags(),
        ];
    }
}

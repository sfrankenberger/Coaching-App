<?php

namespace App\Ai\Werkzeuge;

use App\Models\User;
use App\Models\Wissen;
use App\Support\Zeit;

class WissenSuchen extends Werkzeug
{
    public function name(): string
    {
        return 'wissen_suchen';
    }

    public function beschreibung(): string
    {
        return 'Im Wissensspeicher der Coachin suchen (Second Brain: Regeln, Preise, Abläufe, Gedanken). Leer = die neuesten Einträge.';
    }

    public function schema(): array
    {
        return $this->str(['suche' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'maximum' => 50]]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $q = trim((string) ($args['suche'] ?? ''));

        return Wissen::query()->when($q !== '', fn ($w) => $w->suche($q))->latest()->limit(max(1, min(50, (int) ($args['limit'] ?? 10))))->get()
            ->map(fn (Wissen $w) => ['id' => $w->id, 'titel' => $w->title, 'text' => $w->body, 'tags' => $w->tags ?? [], 'wann' => Zeit::datum($w->created_at), 'quelle' => $w->source])->all();
    }
}

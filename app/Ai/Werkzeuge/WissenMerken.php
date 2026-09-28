<?php

namespace App\Ai\Werkzeuge;

use App\Models\User;
use App\Models\Wissen;

class WissenMerken extends Werkzeug
{
    public function name(): string
    {
        return 'wissen_merken';
    }

    public function beschreibung(): string
    {
        return 'Etwas im Wissensspeicher der Coachin merken (Second Brain), damit es der Assistent später kennt.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'text' => ['type' => 'string'],
            'titel' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
        ], ['text']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        abort_if(trim((string) ($args['text'] ?? '')) === '', 422, 'Kein Text.');
        $w = Wissen::merken($von, $args['text'], $args['titel'] ?? null, $args['tags'] ?? null, 'mcp');

        return ['ok' => true, 'id' => $w->id, 'titel' => $w->title];
    }
}

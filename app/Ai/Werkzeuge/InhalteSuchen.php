<?php

namespace App\Ai\Werkzeuge;

use App\Ai\Assistent;
use App\Models\User;

class InhalteSuchen extends Werkzeug
{
    public function __construct(protected Assistent $assistent) {}

    public function name(): string
    {
        return 'inhalte_suchen';
    }

    public function beschreibung(): string
    {
        return 'Inhalte der App suchen: Lektionen, Impulse, Podcastfolgen, Material, Werkzeuge. Liefert Titel, Art, Kurztext und Link.';
    }

    public function schema(): array
    {
        return $this->str(['suche' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'maximum' => 30]], ['suche']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        return $this->assistent->inhalte((string) ($args['suche'] ?? ''), max(1, min(30, (int) ($args['limit'] ?? 10))));
    }
}

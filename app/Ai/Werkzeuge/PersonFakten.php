<?php

namespace App\Ai\Werkzeuge;

use App\Ai\Assistent;
use App\Models\User;

class PersonFakten extends Werkzeug
{
    public function __construct(protected Assistent $assistent) {}

    public function name(): string
    {
        return 'person_fakten';
    }

    public function beschreibung(): string
    {
        return 'Alles Wichtige zu einer Person: Kurse, Zugänge, Sitzungen, Termine, Aufgaben, Gespräch, Buchungen, Lage.';
    }

    public function schema(): array
    {
        return $this->str($this->wer());
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);

        return ['membership_id' => $m->id] + $this->assistent->personFakten($m);
    }
}

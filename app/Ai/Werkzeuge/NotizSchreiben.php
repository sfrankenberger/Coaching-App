<?php

namespace App\Ai\Werkzeuge;

use App\Models\CoachNote;
use App\Models\User;

class NotizSchreiben extends Werkzeug
{
    public function name(): string
    {
        return 'notiz_schreiben';
    }

    public function beschreibung(): string
    {
        return 'Eine private Notiz zu einer Person speichern (sieht nur die Coachin und das Team, nie die Person).';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str($this->wer() + ['text' => ['type' => 'string']], ['text']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);
        abort_if(trim((string) ($args['text'] ?? '')) === '', 422, 'Kein Text.');
        $n = CoachNote::create(['user_id' => $m->user_id, 'author_id' => $von->id, 'body' => trim($args['text'])]);

        return ['ok' => true, 'note_id' => $n->id] + $this->kurz($m);
    }
}

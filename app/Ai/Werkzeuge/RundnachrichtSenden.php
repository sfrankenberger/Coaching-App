<?php

namespace App\Ai\Werkzeuge;

use App\Models\Program;
use App\Models\User;
use App\Notifications\Rundsendung;

class RundnachrichtSenden extends Werkzeug
{
    public function __construct(protected Rundsendung $rundsendung) {}

    public function name(): string
    {
        return 'rundnachricht_senden';
    }

    public function beschreibung(): string
    {
        return 'Rundnachricht an alle aktiven Mitglieder oder an ein Programm: Push und Telegram (wer es hat), Mail (wer kein Push hat), auf Wunsch ins Gruppengespraech. Ohne bestaetigt=true kommt nur die Empfaengerzahl zurueck.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'an' => ['type' => 'string', 'enum' => ['alle', 'programm', 'begleitung'], 'description' => 'Vorgabe: alle. begleitung = alle, die in einer 1:1 Begleitung sind oder waren'],
            'programm' => ['type' => 'string', 'description' => 'Titel oder Slug des Kurses, wenn an=programm'],
            'titel' => ['type' => 'string'],
            'text' => ['type' => 'string'],
            'url' => ['type' => 'string', 'description' => 'Link hinter dem Knopf, sonst die Startseite'],
            'kanaele' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['push', 'mail']], 'description' => 'Vorgabe: push und mail'],
            'chat' => ['type' => 'boolean', 'description' => 'auch ins Gruppengespraech (nur bei Programm)'],
            'bestaetigt' => ['type' => 'boolean', 'description' => 'true = wirklich senden'],
        ], ['titel', 'text']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $an = in_array($args['an'] ?? 'alle', ['programm', 'begleitung'], true) ? $args['an'] : 'alle';
        $program = null;
        if ($an === 'programm') {
            $s = trim((string) ($args['programm'] ?? ''));
            $program = Program::where('slug', $s)->orWhere('title', 'like', "%$s%")->first();
            abort_unless($program, 404, 'Programm nicht gefunden: '.$s);
        }
        $ids = $this->rundsendung->recipients($an, $program?->id, $von);
        $daten = ['an' => $an, 'program_id' => $program?->id, 'titel' => $args['titel'], 'text' => $args['text'], 'url' => $args['url'] ?? null,
            'kanaele' => array_values(array_intersect((array) ($args['kanaele'] ?? ['push', 'mail']), ['push', 'mail'])) ?: ['push', 'mail'], 'chat' => (bool) ($args['chat'] ?? false), 'persoenlich' => false];
        if (! ($args['bestaetigt'] ?? false)) {
            return ['gesendet' => false, 'empfaenger' => $ids->count(), 'programm' => $program?->title, 'hinweis' => 'Nichts gesendet. Zum Senden bestaetigt=true.'];
        }
        $r = $this->rundsendung->send($daten, $von);

        return ['gesendet' => true, 'programm' => $program?->title] + $r;
    }
}

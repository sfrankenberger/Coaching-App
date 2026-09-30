<?php

namespace App\Ai;

use App\Ai\Werkzeuge\Werkzeug;
use App\Ai\Werkzeuge\Werkzeugkasten;
use App\Models\User;
use App\Tenancy\Branding;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Der Assistent als Gespraech mit Werkzeugen (Claude in der App): die Coachin schreibt, Claude liest ueber die
 * Werkzeuge nach und antwortet. Alles, was etwas aendert (Werkzeug::schreibt), wird erst nach Rueckfrage
 * ausgefuehrt: das Gespraech haelt an, die Coachin bestaetigt oder bricht ab, dann geht es weiter.
 *
 * Zustand (in der Sitzung der Coachin): messages (API-Format), protokoll (fuer die Anzeige) und offen
 * (wartende Schreib-Werkzeuge samt der schon ausgefuehrten Lese-Ergebnisse derselben Runde).
 */
class Dialog
{
    public const RUNDEN = 6;

    public const MERKEN = 40;   // Nachrichten, die mitlaufen

    public function __construct(protected Anthropic $ai, protected Werkzeugkasten $kasten, protected Assistent $assistent, protected Branding $branding) {}

    public static function leer(): array
    {
        return ['messages' => [], 'protokoll' => [], 'offen' => null];
    }

    /** Neue Nachricht der Coachin. Gibt den neuen Zustand zurueck. */
    public function sagen(array $zustand, string $text, User $coach): array
    {
        abort_if($zustand['offen'], 409, 'Erst die offene Rückfrage beantworten.');
        $text = trim($text);
        $zustand['messages'][] = ['role' => 'user', 'content' => $text];
        $zustand['protokoll'][] = ['rolle' => 'du', 'text' => $text];

        return $this->laufen($zustand, $coach);
    }

    /** Rueckfrage beantwortet: ja fuehrt die wartenden Werkzeuge aus, nein meldet Claude den Abbruch. */
    public function entscheiden(array $zustand, bool $ja, User $coach): array
    {
        $offen = $zustand['offen'];
        abort_unless($offen, 409, 'Nichts offen.');
        $ergebnisse = $offen['ergebnisse'];
        foreach ($offen['werkzeuge'] as $w) {
            if ($ja) {
                $ergebnisse[] = $this->ausfuehren($w, $coach, $zustand);
            } else {
                $ergebnisse[] = ['type' => 'tool_result', 'tool_use_id' => $w['id'], 'content' => 'Die Coachin hat das abgebrochen. Nichts wurde geändert. Frag nach, was sie stattdessen möchte.'];
                $zustand['protokoll'][] = ['rolle' => 'werkzeug', 'name' => $w['name'], 'args' => $w['input'], 'ergebnis' => 'Abgebrochen.', 'fehler' => false];
            }
        }
        $zustand['offen'] = null;
        $zustand['messages'][] = ['role' => 'user', 'content' => $ergebnisse];

        return $this->laufen($zustand, $coach);
    }

    protected function laufen(array $zustand, User $coach): array
    {
        $tools = $this->kasten->alle()->values()->map(fn (Werkzeug $w) => ['name' => $w->name(), 'description' => $w->beschreibung(), 'input_schema' => $w->schema()])->all();
        for ($runde = 0; $runde < self::RUNDEN; $runde++) {
            try {
                $r = $this->ai->chat($this->kuerzen($zustand['messages']), $this->system(), $tools, 1500);
            } catch (Throwable $e) {
                report($e);
                $zustand['protokoll'][] = ['rolle' => 'ki', 'text' => 'Die KI antwortet gerade nicht ('.mb_substr($e->getMessage(), 0, 120).'). Versuch es gleich nochmal.'];

                return $zustand;
            }
            $zustand['messages'][] = ['role' => 'assistant', 'content' => $r['content']];
            foreach ($r['content'] as $block) {
                if (($block['type'] ?? '') === 'text' && trim($block['text']) !== '') {
                    $zustand['protokoll'][] = ['rolle' => 'ki', 'text' => trim($block['text'])];
                }
            }
            $aufrufe = array_values(array_filter($r['content'], fn ($b) => ($b['type'] ?? '') === 'tool_use'));
            if ($aufrufe === []) {
                return $zustand;
            }
            $ergebnisse = [];
            $wartend = [];
            foreach ($aufrufe as $a) {
                $w = $this->kasten->finde($a['name']);
                if ($w && $w->schreibt()) {
                    $wartend[] = ['id' => $a['id'], 'name' => $a['name'], 'input' => (array) ($a['input'] ?? [])];
                } else {
                    $ergebnisse[] = $this->ausfuehren(['id' => $a['id'], 'name' => $a['name'], 'input' => (array) ($a['input'] ?? [])], $coach, $zustand);
                }
            }
            if ($wartend) {
                $zustand['offen'] = ['ergebnisse' => $ergebnisse, 'werkzeuge' => $wartend];

                return $zustand;
            }
            $zustand['messages'][] = ['role' => 'user', 'content' => $ergebnisse];
        }
        $zustand['protokoll'][] = ['rolle' => 'ki', 'text' => 'Das waren viele Schritte auf einmal. Sag mir, wie es weitergehen soll.'];

        return $zustand;
    }

    /** Werkzeug ausfuehren, Ergebnis als tool_result; Fehler kommen als Text zurueck, nicht als Absturz. */
    protected function ausfuehren(array $w, User $coach, array &$zustand): array
    {
        try {
            $text = $this->kasten->aufrufen($w['name'], $w['input'], $coach);
            $zustand['protokoll'][] = ['rolle' => 'werkzeug', 'name' => $w['name'], 'args' => $w['input'], 'ergebnis' => mb_substr($text, 0, 600), 'fehler' => false];

            return ['type' => 'tool_result', 'tool_use_id' => $w['id'], 'content' => $text];
        } catch (HttpException $e) {
            $zustand['protokoll'][] = ['rolle' => 'werkzeug', 'name' => $w['name'], 'args' => $w['input'], 'ergebnis' => $e->getMessage(), 'fehler' => true];

            return ['type' => 'tool_result', 'tool_use_id' => $w['id'], 'content' => 'Fehler: '.$e->getMessage(), 'is_error' => true];
        } catch (Throwable $e) {
            report($e);
            $zustand['protokoll'][] = ['rolle' => 'werkzeug', 'name' => $w['name'], 'args' => $w['input'], 'ergebnis' => 'Technischer Fehler.', 'fehler' => true];

            return ['type' => 'tool_result', 'tool_use_id' => $w['id'], 'content' => 'Technischer Fehler beim Werkzeug.', 'is_error' => true];
        }
    }

    /** Nur die letzten Nachrichten mitschicken; angefangen wird immer bei einer Nachricht der Coachin. */
    protected function kuerzen(array $messages): array
    {
        $m = array_slice($messages, -self::MERKEN);
        while ($m && (($m[0]['role'] ?? '') !== 'user' || ! is_string($m[0]['content'] ?? null))) {
            array_shift($m);
        }

        return $m ?: array_slice($messages, -1);
    }

    protected function system(): string
    {
        $coach = $this->branding->coachName();

        return "Du bist der Assistent von {$coach} in ihrer Coaching-App ({$this->branding->appName()}). Du sprichst mit {$coach} oder ihrem Team, per Du, kurz, warm, auf Deutsch (Schweiz, kein ß). "
            .'Du arbeitest mit den Werkzeugen der App: nachschlagen (Personen, Termine, Kontakte, Newsletter, Inhalte, Wissen) und handeln (Person anlegen, Zugang geben, Nachricht, Aufgabe, Termin, Kontakt taggen, Newsletter anlegen und senden, Impuls, Rundnachricht). '
            .'Alles, was etwas ändert, fragt die App vor dem Ausführen bei der Coachin nach; du musst nicht selbst um Erlaubnis bitten, aber sag kurz, was du vorhast. '
            .'Erfinde nichts: was du nicht über ein Werkzeug weisst, weisst du nicht. Nenne bei Personen die membership_id nicht, nur Namen. Zahlen und Ergebnisse aus Werkzeugen gibst du knapp wieder. '
            .'Heute ist '.now()->translatedFormat('l, j. F Y').".\n\n".$this->assistent->wissen();
    }
}

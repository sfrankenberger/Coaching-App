<?php

namespace App\Programs;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Wochenaufgaben mit Art und Wochentag: der passende Knopf an der Karte (Notiz schreiben, Reflexion schreiben,
 * Frage stellen, Aufzeichnung ansehen, Termin buchen), die Faelligkeit aus Woche plus Wochentag,
 * und "Speichern und abhaken": wer aus der Aufgabe heraus schreibt, hakt sie damit ab.
 */
class Wochenaufgabe
{
    /** Der Knopf zur Aufgabe: Ziel, Text, Symbol. Null bei "Abhaken" oder wenn es nichts anzusteuern gibt. */
    public function aktion(Task $t): ?array
    {
        if ($t->isDone()) {
            return null;
        }
        [, $icon, $text] = Task::KINDS[$t->kind] ?? Task::KINDS['haken'];

        $url = match ($t->kind) {
            'notiz' => route('notizen.index', ['aufgabe' => $t->id]).'#neu',
            'reflexion' => route('reflexion.index', ['aufgabe' => $t->id]).'#neu',
            'frage' => $t->program ? route('kurse.fragen', [$t->program, 'aufgabe' => $t->id]).'#neu' : null,
            'aufzeichnung' => ($e = $this->aufzeichnung($t)) ? route('termine.show', $e) : null,
            'termin' => route('buchen.index'),
            default => null,
        };

        return $url ? ['url' => $url, 'text' => $text, 'icon' => $icon] : null;
    }

    /** Die Aufzeichnung zur Aufgabe: der Call der Woche, sonst der letzte Call des Programms mit Aufzeichnung. */
    protected function aufzeichnung(Task $t): ?Event
    {
        $q = Event::query()->where('is_published', true)->whereNotNull('recording_url')->where('program_id', $t->program_id);

        return ($t->step_id ? (clone $q)->where('step_id', $t->step_id)->orderByDesc('starts_at')->first() : null)
            ?? $q->where('starts_at', '<', now())->orderByDesc('starts_at')->first();
    }

    /** Die Aufgabe, aus der heraus gerade geschrieben wird (?aufgabe=ID), nur eigene und offene. */
    public function ausAufgabe(Request $request, User $user): ?Task
    {
        $id = (int) ($request->input('aufgabe_id') ?: $request->query('aufgabe', 0));

        return $id ? Task::where('user_id', $user->id)->whereNull('done_at')->find($id) : null;
    }

    /** Speichern und abhaken: die Aufgabe ist erledigt, sobald das Geschriebene gespeichert ist. */
    public function abhaken(Request $request, User $user): ?Task
    {
        $t = $this->ausAufgabe($request, $user);
        $t?->forceFill(['done_at' => now()])->save();

        return $t;
    }

    /** Anhaenge fuer das neue Element: die Aufgabe selbst haengt dran, damit der Bezug sichtbar bleibt. */
    public function refs(Request $request, User $user, ?array $refs): array
    {
        $refs = $refs ?? [];
        if ($t = $this->ausAufgabe($request, $user)) {
            $refs[] = 'task:'.$t->id;
        }

        return array_values(array_unique($refs));
    }
}

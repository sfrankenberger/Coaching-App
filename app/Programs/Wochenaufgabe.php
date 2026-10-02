<?php

namespace App\Programs;

use App\Models\Event;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    public const TAGE = [
        'reflection_day' => ['reflexion', 'Deine Wochenreflexion', 'Was hat geklappt, was war schwierig, worauf richtest du deinen Fokus?'],
        'question_day' => ['frage', 'Deine Frage für den Fragentag', 'Was beschäftigt dich gerade? Schreib deine Frage, damit sie am Fragentag aufgegriffen werden kann.'],
    ];

    /**
     * Reflexions- und Fragentag der Woche sind Aufgaben wie im alten Bereich: abhakbar, mit Knopf zum Schreiben.
     * Werden je Person einmal angelegt, sobald sie die Woche oder die Startseite sieht (nicht fuer das Team).
     */
    public function sicherstellen(User $user, Program $program, ProgramStep $step): void
    {
        if ($user->canManageCurrentTenant()) {
            return;
        }
        $tage = Event::where('program_id', $program->id)->where('step_id', $step->id)->where('is_published', true)->whereNull('cancelled_at')
            ->whereIn('type', array_keys(self::TAGE))->orderBy('starts_at')->get()->unique('type');
        if ($tage->isEmpty()) {
            return;
        }
        $vorhanden = Task::where('user_id', $user->id)->where('step_id', $step->id)->whereIn('kind', ['reflexion', 'frage'])->pluck('kind');
        foreach ($tage as $e) {
            [$kind, $titel, $text] = self::TAGE[$e->type];
            if ($vorhanden->contains($kind)) {
                continue;
            }
            Task::withoutEvents(fn () => Task::create([
                'tenant_id' => $program->tenant_id, 'user_id' => $user->id, 'program_id' => $program->id, 'step_id' => $step->id,
                'title' => $titel, 'body' => $text, 'kind' => $kind, 'weekday' => (int) $e->starts_at->isoWeekday(), 'due_at' => $e->starts_at->toDateString(),
                'visibility' => 'coach', 'source' => 'program',
            ]));
        }
    }

    /** Aufgaben der Woche fuer die Startseite: offen zuerst, dann erledigt. */
    public function derWoche(User $user, ProgramStep $step): Collection
    {
        return Task::where('user_id', $user->id)->where('step_id', $step->id)
            ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->orderBy('id')->get();
    }

    /**
     * Wer eine Reflexion schreibt oder eine Frage stellt, hat die Aufgabe dazu erledigt, auch ohne den Knopf
     * an der Karte: die offene Aufgabe dieser Art in der Woche (oder der laufenden Woche des Programms) wird abgehakt.
     */
    public function abhakenArt(User $user, string $kind, ?int $stepId, ?int $programId = null): ?Task
    {
        if (! $stepId && $programId && ($p = Program::with('steps')->find($programId)) && $p->pacing === 'weekly') {
            $stepId = $p->steps->filter(fn (ProgramStep $s) => $s->isUnlocked($p))->sortByDesc('position')->first()?->id;
        }
        if (! $stepId) {
            return null;
        }
        $t = Task::where('user_id', $user->id)->where('step_id', $stepId)->where('kind', $kind)->whereNull('done_at')->orderBy('id')->first();
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

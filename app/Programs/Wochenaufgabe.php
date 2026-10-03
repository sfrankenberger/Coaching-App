<?php

namespace App\Programs;

use App\Coach\Ansicht;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Question;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
            'frage' => ($p = $this->programm($t)) ? route('kurse.fragen', [$p, 'aufgabe' => $t->id]).'#neu' : null,
            'aufzeichnung' => ($e = $this->aufzeichnung($t)) ? route('termine.show', $e) : null,
            'termin' => route('buchen.index'),
            default => null,
        };

        return $url ? ['url' => $url, 'text' => $text, 'icon' => $icon] : null;
    }

    /** Das Programm der Aufgabe mit Slug fuer Links, auch wenn die Liste es nur mit Titel geladen hat. */
    protected function programm(Task $t): ?Program
    {
        if (! $t->program_id) {
            return null;
        }

        return ($t->relationLoaded('program') && $t->program && isset($t->program->slug)) ? $t->program : Program::find($t->program_id);
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

        return $id ? Task::where('user_id', $user->id)->open()->find($id) : null;
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

    /** Bekommt diese Person Wochenaufgaben? Teilnehmerinnen immer, das Team nur in der Teilnehmer-Ansicht (zum Mitmachen und Testen). */
    public static function teilnehmerin(User $user): bool
    {
        return ! $user->canManageCurrentTenant() || Ansicht::wieTeilnehmerin($user);
    }

    /** Die laufende Woche eines getakteten Programms: der zuletzt freigeschaltete Schritt. */
    public static function aktuellerSchritt(Program $program): ?ProgramStep
    {
        if ($program->pacing !== 'weekly') {
            return null;
        }

        return $program->steps->filter(fn (ProgramStep $s) => $s->isUnlocked($program))->sortByDesc('position')->first();
    }

    /** Fuer alle getakteten Programme der Person die Aufgaben der laufenden Woche anlegen (Startseite, Meine Aufgaben). */
    public function sicherstellenAlle(User $user, Collection $programs): void
    {
        foreach ($programs as $p) {
            if ($step = self::aktuellerSchritt($p)) {
                $this->sicherstellen($user, $p, $step);
            }
        }
    }

    /**
     * Beim Wochenstart fuer alle im Programm anlegen, nicht erst beim ersten Besuch: so stehen Reflexion und Frage
     * sofort unter Meine Aufgaben und in den Erinnerungen. Nur aktive Teilnehmerinnen (Rolle member oder client).
     * Gibt die Zahl der neu angelegten Aufgaben zurueck.
     */
    public function anlegenFuerAlle(Program $program): int
    {
        $step = self::aktuellerSchritt($program);
        if (! $step) {
            return 0;
        }
        $aktiv = Membership::where('status', 'active')->whereIn('role', ['member', 'client'])->pluck('user_id');
        $n = 0;
        foreach (User::whereIn('id', ProgramMember::where('program_id', $program->id)->pluck('user_id')->intersect($aktiv))->get() as $user) {
            $n += $this->sicherstellen($user, $program, $step, erzwingen: true);
        }

        return $n;
    }

    /**
     * Reflexions- und Fragentag der Woche sind Aufgaben wie im alten Bereich: abhakbar, mit Knopf zum Schreiben,
     * auch ohne etwas geschrieben zu haben. Je Person einmal angelegt: beim Wochenstart fuer alle (Lauf), sonst
     * sobald sie die Woche, die Startseite oder Meine Aufgaben sieht. Gibt die Zahl der neuen Aufgaben zurueck.
     */
    public function sicherstellen(User $user, Program $program, ProgramStep $step, bool $erzwingen = false): int
    {
        if (! $erzwingen && ! self::teilnehmerin($user)) {
            return 0;
        }
        $tage = Event::where('program_id', $program->id)->where('step_id', $step->id)->where('is_published', true)->whereNull('cancelled_at')
            ->whereIn('type', array_keys(self::TAGE))->orderBy('starts_at')->get()->unique('type');
        if ($tage->isEmpty()) {
            return 0;
        }
        $vorhanden = Task::where('user_id', $user->id)->where('step_id', $step->id)->whereIn('kind', ['reflexion', 'frage'])->pluck('kind');
        $n = 0;
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
            $n++;
        }

        return $n;
    }

    /**
     * Was zur Aufgabe geschrieben wurde: die Reflexion oder die Frage dieser Woche, sonst null (dann wurde nur abgehakt).
     * Das Dossier zeigt dem Team damit, ob hinter dem Haken ein Text steht.
     */
    public function geschrieben(Task $t): ?Model
    {
        if (! in_array($t->kind, ['reflexion', 'frage'], true) || ! $t->user_id) {
            return null;
        }
        $von = ($t->due_at ?? $t->created_at)->copy()->subDays(7)->startOfDay()->utc();
        $bis = ($t->due_at ?? $t->created_at)->copy()->addDays(2)->endOfDay()->utc();
        if ($t->kind === 'reflexion') {
            return Reflection::where('user_id', $t->user_id)->where(fn ($q) => $q->where('step_id', $t->step_id ?? 0)
                ->orWhere(fn ($w) => $w->whereNull('step_id')->whereBetween('created_at', [$von, $bis])))->latest()->first();
        }

        return Question::where('user_id', $t->user_id)->when($t->program_id, fn ($q) => $q->where('program_id', $t->program_id))
            ->whereBetween('created_at', [$von, $bis])->latest()->first();
    }

    /** Aufgaben der Woche fuer die Startseite: offen zuerst, dann erledigt. */
    public function derWoche(User $user, ProgramStep $step): Collection
    {
        return Task::where('user_id', $user->id)->where('step_id', $step->id)
            ->orderByRaw('CASE WHEN done_at IS NULL AND skipped_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->orderBy('id')->get();
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
        $t?->forceFill(['skipped_at' => null]);
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

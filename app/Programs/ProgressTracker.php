<?php

namespace App\Programs;

use App\Models\Answer;
use App\Models\Program;
use App\Models\Progress;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Fortschritt einer Person in einem Programm: erledigte Einheiten und
 * ausgefuellte Uebungsteile.
 */
class ProgressTracker
{
    /** Erledigte unit_ids der Person im Programm. */
    public function completedUnitIds(User $user, Program $program): Collection
    {
        return Progress::query()
            ->where('user_id', $user->id)
            ->whereIn('unit_id', $program->units->pluck('id'))
            ->whereNotNull('completed_at')
            ->pluck('unit_id');
    }

    /** ['done' => 3, 'total' => 12, 'percent' => 25, 'next' => Unit|null] */
    public function summary(User $user, Program $program): array
    {
        $units = $program->orderedUnits();
        $done = $this->completedUnitIds($user, $program);
        $next = $units->first(fn (Unit $u) => ! $done->contains($u->id)) ?? $units->first();
        $total = $units->count();
        $n = $units->filter(fn (Unit $u) => $done->contains($u->id))->count();

        return ['done' => $n, 'total' => $total, 'percent' => $total ? (int) round($n / $total * 100) : 0, 'next' => $next];
    }

    public function toggle(User $user, Unit $unit, ?bool $done = null): bool
    {
        $row = Progress::firstOrNew(['user_id' => $user->id, 'unit_id' => $unit->id]);
        $done ??= $row->completed_at === null;
        $row->completed_at = $done ? now() : null;
        $row->save();

        return $done;
    }

    /** Antworten der Person auf die Uebungsteile einer Einheit, nach exercise_id. */
    public function answersFor(User $user, Unit $unit): Collection
    {
        return Answer::query()
            ->where('user_id', $user->id)
            ->whereIn('exercise_id', $unit->exercises->pluck('id'))
            ->get()
            ->keyBy('exercise_id');
    }

    /** ['filled' => 2, 'total' => 5, 'percent' => 40] fuer eine Uebungseinheit. */
    public function exerciseSummary(User $user, Unit $unit, ?Collection $answers = null): array
    {
        $answers ??= $this->answersFor($user, $unit);
        $answerable = $unit->answerableExercises();
        $filled = $answerable->filter(fn ($e) => $answers->get($e->id)?->isFilled())->count();
        $total = $answerable->count();

        return ['filled' => $filled, 'total' => $total, 'percent' => $total ? (int) round($filled / $total * 100) : 0];
    }
}

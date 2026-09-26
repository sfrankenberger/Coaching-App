<?php

namespace App\Observers;

use App\Models\ProgramMember;
use App\Models\Task;

/**
 * Wer spaeter in ein Programm kommt, bekommt die offenen Kursaufgaben (source "program")
 * auch, statt leer auszugehen. Vergangene Fristen werden nicht nachgetragen.
 */
class ProgramMemberObserver
{
    public function created(ProgramMember $member): void
    {
        $vorlagen = Task::query()
            ->where('program_id', $member->program_id)
            ->where('source', 'program')
            ->whereNotNull('assigned_by')           // nur Aufgaben der Coachin, keine eigenen aus Einheiten
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()->startOfDay()))
            ->orderBy('id')->get()
            ->unique(fn (Task $t) => $t->title.'|'.$t->step_id.'|'.$t->due_at?->toDateString());

        foreach ($vorlagen as $v) {
            $schon = Task::where('user_id', $member->user_id)->where('program_id', $member->program_id)
                ->where('title', $v->title)->where('step_id', $v->step_id)->exists();
            if ($schon) {
                continue;
            }
            // Leise anlegen: keine Einzelmeldung je Aufgabe beim Eintritt (ohne Events, darum tenant_id ausdruecklich)
            Task::withoutEvents(fn () => Task::create([
                'tenant_id' => $member->tenant_id, 'user_id' => $member->user_id, 'program_id' => $v->program_id, 'step_id' => $v->step_id,
                'title' => $v->title, 'body' => $v->body, 'due_at' => $v->due_at, 'due_time' => $v->due_time,
                'is_daily' => $v->is_daily, 'visibility' => $v->visibility, 'assigned_by' => $v->assigned_by, 'source' => 'program',
            ]));
        }
    }
}

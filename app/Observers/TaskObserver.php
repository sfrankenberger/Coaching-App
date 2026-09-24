<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;

/** Eine Aufgabe von der Coachin: die Person erfaehrt es. */
class TaskObserver
{
    public function created(Task $task): void
    {
        if (! $task->assigned_by || $task->assigned_by === $task->user_id) {
            return;
        }
        $von = User::find($task->assigned_by)?->vorname() ?: 'Deine Coachin';

        app(Notifier::class)->send([$task->user_id], new Nachricht(
            titel: "{$von} hat dir eine Aufgabe gegeben",
            text: $task->title.($task->due_at ? ' (bis '.$task->due_at->translatedFormat('j. F').')' : ''),
            url: route('aufgaben.index').'#aufgabe-'.$task->id,
            anlass: 'aufgabe',
            tag: 'aufgabe-'.$task->id,
            knopf: 'Zu den Aufgaben',
        ));
    }
}

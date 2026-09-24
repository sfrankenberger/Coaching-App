<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\ProgramMember;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use Illuminate\Support\Collection;

/** Sobald eine Aufzeichnung eingetragen ist, erfahren es die Teilnehmerinnen. */
class EventObserver
{
    public function saved(Event $event): void
    {
        if (! $event->wasChanged('recording_url') || blank($event->recording_url) || $event->recording_notified_at) {
            return;
        }
        if (! $event->is_published) {
            return;
        }

        $ids = self::recipients($event);
        if ($ids->isEmpty()) {
            return;
        }

        app(Notifier::class)->send($ids, new Nachricht(
            titel: 'Aufzeichnung ist da: '.$event->title,
            text: 'Die Aufzeichnung vom '.$event->starts_at->translatedFormat('j. F').' steht für dich bereit. Nimm dir die Zeit dafür, wann es dir passt.',
            url: route('termine.show', $event),
            anlass: 'aufzeichnung',
            tag: 'aufzeichnung-'.$event->id,
            mailWennKeinPush: false,
            knopf: 'Zur Aufzeichnung',
        ));
        $event->forceFill(['recording_notified_at' => now()])->saveQuietly();
    }

    /** Wer zu einem Termin gehoert: 1:1 die Person, sonst alle im Programm (ohne Abgesagte). */
    public static function recipients(Event $event): Collection
    {
        if ($event->user_id) {
            return collect([$event->user_id]);
        }
        if (! $event->program_id) {
            return collect();
        }
        $declined = $event->attendees()->where('status', 'declined')->pluck('user_id');

        return ProgramMember::where('program_id', $event->program_id)->pluck('user_id')->diff($declined)->values();
    }
}

<?php

namespace App\Recordings;

use App\Models\Event;
use App\Models\ProgramMember;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use Illuminate\Support\Collection;

/**
 * Aufzeichnung bewusst freigeben (wie nva_e_versenden): Mail mit Zusammenfassung und/oder Push
 * an alle im Kurs (auch wer abgesagt hat, gerade die brauchen sie) bzw. an die Person der 1:1-Sitzung.
 */
class Freigabe
{
    public function __construct(protected Notifier $notifier) {}

    public function empfaenger(Event $event): Collection
    {
        if ($event->user_id) {
            return collect([$event->user_id]);
        }

        return $event->program_id ? ProgramMember::where('program_id', $event->program_id)->where('role_in_program', '!=', 'coach')->pluck('user_id') : collect();
    }

    /** @param  array<int, string>  $wege  mail, push */
    public function freigeben(Event $event, array $wege = ['mail', 'push']): int
    {
        abort_if($event->recording_notified_at, 409, 'Diese Aufzeichnung wurde schon verschickt.');
        abort_unless($event->hasRecording(), 422, 'Am Termin ist keine Aufzeichnung.');
        $ids = $this->empfaenger($event);
        $einzel = (bool) $event->user_id;

        if ($ids->isNotEmpty() && $wege !== []) {
            $mail = in_array('mail', $wege, true);
            $push = in_array('push', $wege, true);
            $nachricht = new Nachricht(
                titel: ($einzel ? 'Eure Sitzung ist da: ' : 'Neue Aufzeichnung: ').$event->title,
                text: 'Die Aufzeichnung '.($einzel ? 'eurer Sitzung ' : '').'vom '.$event->starts_at->translatedFormat('j. F').' ist da.',
                url: route('termine.show', $event),
                anlass: 'aufzeichnung',
                tag: 'aufzeichnung-'.$event->id,
                mailWennKeinPush: $mail,
                mailBetreff: ($einzel ? 'Eure Sitzung: ' : 'Aufzeichnung: ').$event->title,
                knopf: 'Jetzt ansehen',
                html: $mail ? $event->summary : null,
                mailImmer: $mail && $push,
            );
            if ($push) {
                $this->notifier->send($ids, $nachricht);
            } else {
                // Nur Mail: Push und Telegram auslassen
                foreach ($ids as $id) {
                    $user = User::find($id);
                    if ($user && $this->notifier->channelsFor($user, $nachricht) !== []) {
                        $user->notify(new AppNotification($nachricht, ['mail'], (int) $event->tenant_id));
                    }
                }
            }
        }
        $event->forceFill(['recording_notified_at' => now(), 'recording_status' => 'freigegeben'])->saveQuietly();

        return $ids->count();
    }
}

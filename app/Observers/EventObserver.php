<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\ProgramMember;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use Illuminate\Support\Collection;

/**
 * Neue Termine sofort melden (wie lea-club-mailster "Neuer Termin fuer dich"): Gruppentermine an
 * alle im Programm, 1:1-Termine an die Person. Aufzeichnungen meldet nicht mehr das Speichern des
 * Links, sondern die bewusste Freigabe (App\Recordings\Freigabe, Wache).
 */
class EventObserver
{
    public const MELDEN = ['group_call', 'one_on_one', 'qa', 'webinar'];

    public function created(Event $event): void
    {
        if (! $event->is_published || ! in_array($event->type, self::MELDEN, true) || $event->starts_at->isPast()) {
            return;
        }
        // Selbst gebucht (Terminvorschlag, Buchung): die Person weiss es schon
        if ($event->user_id && (int) ($event->settings['gebucht_von'] ?? 0) === (int) $event->user_id) {
            return;
        }
        $ids = self::recipients($event);
        if ($ids->isEmpty()) {
            return;
        }
        $wann = $event->starts_at->translatedFormat('l, j. F, H:i').' Uhr';
        app(Notifier::class)->send($ids, new Nachricht(
            titel: ($event->user_id ? 'Neuer Termin für dich: ' : 'Neuer Termin: ').$event->title,
            text: $wann.'. Du findest ihn unter Termine und kannst ihn in deinen Kalender übernehmen.',
            url: route('termine.show', $event),
            anlass: 'termin_neu',
            tag: 'termin-neu-'.$event->id,
            knopf: 'Zum Termin',
        ));
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

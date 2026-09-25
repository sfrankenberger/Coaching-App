<?php

namespace App\Booking;

use App\Chat\Chat;
use App\Coach\Lage;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Buchen und Absagen (wie nvc_buchen, nvc_absagen). */
class Buchung
{
    public function __construct(
        protected CurrentTenant $current,
        protected Verfuegbarkeit $verfuegbarkeit,
        protected GoogleCalendar $google,
        protected Notifier $notifier,
        protected Chat $chat,
    ) {}

    /** Darf die Person diese Art buchen? Gibt null oder den Grund zurueck. */
    public function hindernis(User $user, BookingType $art): ?string
    {
        if (! $art->is_active) {
            return 'Diese Art ist gerade nicht buchbar.';
        }
        if ($art->is_open) {
            $offen = Booking::where('user_id', $user->id)->where('booking_type_id', $art->id)->where('status', 'gebucht')->where('starts_at', '>', now())->exists();

            return $offen ? 'Du hast dafür schon einen Termin.' : null;
        }
        $k = app(Lage::class)->kontingent($user);

        return ! $k ? 'Dafür brauchst du eine Begleitung mit Sitzungen.' : ($k['offen'] < 1 ? 'Deine Sitzungen sind aufgebraucht oder schon verplant.' : null);
    }

    public function buchen(User $user, BookingType $art, Carbon $start, array $antworten = []): Booking
    {
        abort_if($grund = $this->hindernis($user, $art), 403, $grund);

        return Cache::lock('buchung-'.$this->current->id(), 20)->block(10, function () use ($user, $art, $start, $antworten) {
            // Direkt vor dem Speichern noch einmal frisch pruefen
            $frei = $this->verfuegbarkeit->zeiten($art, true)->contains(fn (Carbon $z) => $z->getTimestamp() === $start->getTimestamp());
            abort_unless($frei, 409, 'Diese Zeit ist leider gerade weggegangen. Such dir eine andere aus.');

            $tenant = $this->current->getOrFail();
            $ende = $start->copy()->addMinutes($art->duration);
            [$booking, $event] = DB::transaction(function () use ($user, $art, $start, $ende, $antworten, $tenant) {
                $event = Event::create([
                    'title' => $art->event_title ?: $art->title,
                    'type' => 'one_on_one',
                    'user_id' => $user->id,
                    'starts_at' => $start,
                    'ends_at' => $ende,
                    'zoom_url' => $tenant->setting('booking.zoom_url') ?: null,
                    'location' => $tenant->setting('booking.zoom_url') ? 'Online via Zoom' : null,
                    'is_published' => true,
                    'settings' => ['gebucht_von' => $user->id, 'buchungsart' => $art->key],
                ]);
                $booking = Booking::create([
                    'event_id' => $event->id, 'user_id' => $user->id, 'booking_type_id' => $art->id,
                    'answers' => array_values(array_filter($antworten, fn ($a) => filled($a['antwort'] ?? null))),
                    'starts_at' => $start, 'block_ends_at' => $start->copy()->addMinutes($art->blockMinuten()), 'booked_by' => $user->id,
                ]);

                return [$booking, $event];
            });

            try {
                $text = collect($booking->answers)->map(fn ($a) => $a['frage']."\n".$a['antwort'])->implode("\n\n");
                $booking->forceFill(['google_event_id' => $this->google->anlegen($art->title.' · '.$user->name, $start, $start->copy()->addMinutes($art->blockMinuten()), trim($text."\n\n".$user->email))])->save();
            } catch (Throwable $e) {
                report($e);
            }

            $wann = $start->translatedFormat('l, j. F, H:i').' Uhr';
            $this->notifier->send([$user->id], new Nachricht(
                titel: 'Gebucht: '.$event->title,
                text: $wann.'. Den Zoom-Link und den Kalendereintrag findest du beim Termin.',
                url: route('termine.show', $event),
                anlass: 'buchung',
                tag: 'buchung-'.$booking->id,
                knopf: 'Zum Termin',
            ));
            $this->teamMelden($user, 'Neue Buchung: '.$user->name, $art->title.', '.$wann.($booking->answers ? "\n\n".Str::limit(collect($booking->answers)->pluck('antwort')->implode(' / '), 300) : ''));

            return $booking;
        });
    }

    public function absagen(Booking $booking, User $von): void
    {
        abort_unless($booking->istAktiv(), 409, 'Dieser Termin ist schon abgesagt.');
        $team = $von->canManageCurrentTenant();
        abort_unless($team || $booking->user_id === $von->id, 403);
        $frist = (int) ($this->current->get()?->setting('booking.cancel_hours', 2) ?? 2);
        abort_if(! $team && $booking->starts_at->lt(now()->addHours($frist)), 422, "Absagen geht bis {$frist} Stunden vorher. Schreib mir bitte direkt.");

        $booking->forceFill(['status' => 'abgesagt', 'cancelled_at' => now()])->save();
        $booking->event?->forceFill(['is_published' => false])->save();
        if ($booking->google_event_id) {
            try {
                $this->google->loeschen($booking->google_event_id);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $wann = $booking->starts_at->translatedFormat('l, j. F, H:i').' Uhr';
        if ($team) {
            $this->notifier->send([$booking->user_id], new Nachricht(titel: 'Termin abgesagt', text: 'Dein Termin am '.$wann.' fällt aus. Buch dir gern eine neue Zeit.', url: route('buchen.index'), anlass: 'buchung', tag: 'buchung-'.$booking->id));
        } else {
            $this->teamMelden($booking->user, 'Abgesagt: '.$booking->user->name, ($booking->type?->title ?? 'Sitzung').', '.$wann);
        }
    }

    protected function teamMelden(User $person, string $titel, string $text): void
    {
        $m = Membership::where('user_id', $person->id)->first();
        $this->notifier->send($this->chat->teamIds(), new Nachricht(
            titel: $titel, text: $text,
            url: $m ? MembershipResource::getUrl('dossier', ['record' => $m], panel: 'coach') : null,
            anlass: 'system', tag: 'buchung-team-'.$person->id,
        ));
    }
}

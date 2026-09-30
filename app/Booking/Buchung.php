<?php

namespace App\Booking;

use App\Chat\Chat;
use App\Coach\Lage;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Support\Anhaenge;
use App\Support\Ics;
use App\Support\Zeit;
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

    public function buchen(User $user, BookingType $art, Carbon $start, array $antworten = [], bool $melden = true, ?string $herkunft = null, array $refs = []): Booking
    {
        abort_if($grund = $this->hindernis($user, $art), 403, $grund);

        return Cache::lock('buchung-'.$this->current->id(), 20)->block(10, function () use ($user, $art, $start, $antworten, $melden, $herkunft, $refs) {
            // Direkt vor dem Speichern noch einmal frisch pruefen
            $frei = $this->verfuegbarkeit->zeiten($art, true)->contains(fn (Carbon $z) => $z->getTimestamp() === $start->getTimestamp());
            abort_unless($frei, 409, 'Diese Zeit ist leider gerade weggegangen. Such dir eine andere aus.');

            $tenant = $this->current->getOrFail();
            $ende = $start->copy()->addMinutes($art->duration);
            [$booking, $event] = DB::transaction(function () use ($user, $art, $start, $ende, $antworten, $tenant, $herkunft, $refs) {
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
                    'herkunft' => $herkunft ? mb_substr($herkunft, 0, 120) : null,
                    'starts_at' => $start, 'block_ends_at' => $start->copy()->addMinutes($art->blockMinuten()), 'booked_by' => $user->id,
                ]);
                if ($refs) {
                    app(Anhaenge::class)->speichern($booking, $refs, $user);
                }

                return [$booking, $event];
            });

            try {
                $text = collect($booking->answers)->map(fn ($a) => $a['frage']."\n".$a['antwort'])->implode("\n\n");
                $booking->forceFill(['google_event_id' => $this->google->anlegen($art->title.' · '.$user->name, $start, $start->copy()->addMinutes($art->blockMinuten()), trim($text."\n\n".$user->email))])->save();
            } catch (Throwable $e) {
                report($e);
            }

            $wann = Zeit::wann($start);
            $melden && $this->bestaetigen($user, $event, $booking, 'Gebucht: '.$event->title, $wann.'. Den Zoom-Link findest du beim Termin, die Kalenderdatei hängt an der Mail.');
            $this->teamMelden($user, 'Neue Buchung: '.$user->name, $art->title.', '.$wann.($herkunft ? "\nKam über: ".$herkunft : '').($refs ? "\nMitgegeben: ".count($refs) : '')
                .($booking->answers ? "\n\n".Str::limit(collect($booking->answers)->pluck('antwort')->implode(' / '), 300) : ''));

            return $booking;
        });
    }

    /**
     * Fester Termin durch das Team oder aus einem Terminvorschlag: Termin, Buchung, Google-Eintrag,
     * Bestaetigung mit Kalenderdatei. Prueft gegen den Kalender, damit nichts doppelt belegt wird.
     */
    public function fest(User $person, Carbon $start, int $dauer, User $von, ?string $titel = null, ?string $quelle = null, bool $melden = true): Booking
    {
        // Die Person selbst (aus einem Vorschlag) bucht nur in die Zukunft; das Team darf auch nachtragen
        abort_unless($start->isFuture() || $von->id !== $person->id, 422, 'Die Zeit liegt in der Vergangenheit.');
        $tenant = $this->current->getOrFail();
        $ende = $start->copy()->addMinutes($dauer);
        $titel = filled($titel) ? trim($titel) : (string) ($tenant->setting('termine.einzel_titel') ?: 'Einzelsitzung');

        return Cache::lock('buchung-'.$this->current->id(), 20)->block(10, function () use ($person, $start, $ende, $von, $titel, $quelle, $tenant, $melden) {
            abort_if($start->isFuture() && $this->belegt($start, $ende, $person), 409, 'Da ist schon etwas im Kalender. Bitte eine andere Zeit wählen.');

            [$booking, $event] = DB::transaction(function () use ($person, $start, $ende, $von, $titel, $quelle, $tenant) {
                $event = Event::create([
                    'title' => $titel, 'type' => 'one_on_one', 'user_id' => $person->id, 'starts_at' => $start, 'ends_at' => $ende,
                    'zoom_url' => $tenant->setting('termine.einzel_zoom_url') ?: ($tenant->setting('booking.zoom_url') ?: null),
                    'location' => ($tenant->setting('termine.einzel_zoom_url') ?: $tenant->setting('booking.zoom_url')) ? 'Online via Zoom' : null,
                    'is_published' => true,
                    'settings' => ['gebucht_von' => $von->id, 'quelle' => $quelle],
                ]);
                $booking = Booking::create([
                    'event_id' => $event->id, 'user_id' => $person->id, 'booking_type_id' => null, 'answers' => [],
                    'herkunft' => $quelle, 'starts_at' => $start, 'block_ends_at' => $ende, 'booked_by' => $von->id,
                ]);

                return [$booking, $event];
            });

            try {
                if ($start->isFuture()) {
                    $booking->forceFill(['google_event_id' => $this->google->anlegen($titel.' · '.$person->name, $start, $ende, (string) $person->email)])->save();
                }
            } catch (Throwable $e) {
                report($e);
            }

            if ($melden && $start->isFuture()) {
                $wann = Zeit::wann($start);
                $this->bestaetigen($person, $event, $booking, 'Dein Termin: '.$titel, $wann.'. Die Kalenderdatei hängt an der Mail, verschieben oder absagen kannst du beim Termin.');
            }

            return $booking;
        });
    }

    /** Ist im Kalender oder bei der Person schon etwas in dieser Zeit? */
    public function belegt(Carbon $start, Carbon $ende, ?User $person = null): bool
    {
        if ($person && Event::where('user_id', $person->id)->where('is_published', true)->where('starts_at', '<', $ende)->where(fn ($q) => $q->where('ends_at', '>', $start)->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '>', $start->copy()->subHour())))->exists()) {
            return true;
        }
        if (! $this->google->konfiguriert()) {
            return false;
        }
        try {
            $wort = preg_replace('~\s+~u', '', mb_strtolower((string) ($this->current->get()?->setting('booking.block_keyword') ?: 'Coaching')));
            foreach ($this->google->eintraege($start->copy()->startOfDay(), $ende->copy()->endOfDay()) as $e) {
                $s = $e['start'] instanceof Carbon ? $e['start'] : Carbon::parse($e['start']);
                $z = $e['ende'] instanceof Carbon ? $e['ende'] : Carbon::parse($e['ende']);
                $block = $wort !== '' && str_contains(preg_replace('~\s+~u', '', mb_strtolower((string) $e['summary'])), $wort);
                if (! ($e['frei'] ?? false) && ! $block && $s->lt($ende) && $z->gt($start)) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return false;
    }

    /** Bestaetigung an die Person: in der App, per Push und immer per Mail mit Kalenderdatei. */
    protected function bestaetigen(User $user, Event $event, Booking $booking, string $titel, string $text): void
    {
        $this->notifier->send([$user->id], new Nachricht(
            titel: $titel, text: $text, url: route('termine.show', $event), anlass: 'buchung', tag: 'buchung-'.$booking->id,
            mailImmer: true, knopf: 'Zum Termin', anhang: ['name' => 'termin-'.$event->id.'.ics', 'inhalt' => Ics::datei($event), 'typ' => 'text/calendar'],
        ));
    }

    public function absagen(Booking $booking, User $von): void
    {
        abort_unless($booking->istAktiv(), 409, 'Dieser Termin ist schon abgesagt.');
        $team = $von->canManageCurrentTenant();
        abort_unless($team || $booking->user_id === $von->id, 403);
        $frist = (int) ($this->current->get()?->setting('booking.cancel_hours', 2) ?? 2);
        abort_if(! $team && $booking->starts_at->lt(now()->addHours($frist)), 422, "Absagen geht bis {$frist} Stunden vorher. Schreib mir bitte direkt.");

        $booking->forceFill(['status' => 'abgesagt', 'cancelled_at' => now()])->save();
        $booking->event?->forceFill(['is_published' => false, 'cancelled_at' => now()])->save();
        if ($booking->google_event_id) {
            try {
                $this->google->loeschen($booking->google_event_id);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $wann = Zeit::wann($booking->starts_at);
        if ($team) {
            $this->notifier->send([$booking->user_id], new Nachricht(titel: 'Termin abgesagt', text: 'Dein Termin am '.$wann.' fällt aus. Buch dir gern eine neue Zeit.', url: route('buchen.index'), anlass: 'buchung', tag: 'buchung-'.$booking->id));
        } else {
            $this->teamMelden($booking->user, 'Abgesagt: '.$booking->user->name, ($booking->type?->title ?? 'Sitzung').', '.$wann);
        }
    }

    /**
     * Verschieben legt den bestehenden Termin um (kein zweiter Termin, wie der Fehler im alten Bereich).
     * Die Person braucht eine freie Zeit aus dem Raster und dieselbe Frist wie beim Absagen; das Team
     * darf jede Zeit setzen (Bauregel 5: vereinbart ist vereinbart).
     */
    public function verschieben(Booking $booking, Carbon $neu, User $von): Booking
    {
        abort_unless($booking->istAktiv(), 409, 'Dieser Termin ist schon abgesagt.');
        $team = $von->canManageCurrentTenant();
        abort_unless($team || $booking->user_id === $von->id, 403);
        $frist = (int) ($this->current->get()?->setting('booking.cancel_hours', 2) ?? 2);
        abort_if(! $team && $booking->starts_at->lt(now()->addHours($frist)), 422, "Verschieben geht bis {$frist} Stunden vorher. Schreib mir bitte direkt.");
        abort_unless($neu->isFuture(), 422, 'Die neue Zeit liegt in der Vergangenheit.');
        $art = $booking->type;
        $dauer = $art?->duration ?: (int) max(15, $booking->event ? $booking->event->starts_at->diffInMinutes($booking->event->ends_at ?? $booking->event->starts_at) : 60);

        return Cache::lock('buchung-'.$this->current->id(), 20)->block(10, function () use ($booking, $neu, $team, $art, $dauer) {
            if (! $team && $art) {
                $frei = $this->verfuegbarkeit->zeiten($art, true)->contains(fn (Carbon $z) => $z->getTimestamp() === $neu->getTimestamp());
                abort_unless($frei, 409, 'Diese Zeit ist leider gerade weggegangen. Such dir eine andere aus.');
            }
            $alt = $booking->starts_at->copy();
            DB::transaction(function () use ($booking, $neu, $art, $dauer) {
                $booking->event?->forceFill(['starts_at' => $neu, 'ends_at' => $neu->copy()->addMinutes($dauer)])->save();
                $booking->forceFill(['starts_at' => $neu, 'block_ends_at' => $neu->copy()->addMinutes($art ? $art->blockMinuten() : $dauer)])->save();
            });
            try {
                if ($booking->google_event_id) {
                    $this->google->loeschen($booking->google_event_id);
                }
                $titel = ($art?->title ?: ($booking->event?->title ?: 'Sitzung')).' · '.$booking->user->name;
                $booking->forceFill(['google_event_id' => $this->google->anlegen($titel, $neu, $neu->copy()->addMinutes($art ? $art->blockMinuten() : $dauer), $booking->user->email)])->save();
            } catch (Throwable $e) {
                report($e);
            }
            $wann = Zeit::wann($neu);
            if ($team) {
                $this->notifier->send([$booking->user_id], new Nachricht(titel: 'Termin verschoben', text: 'Dein Termin ist jetzt am '.$wann.' (vorher '.Zeit::wann($alt).').', url: $booking->event ? route('termine.show', $booking->event) : route('termine.index'), anlass: 'buchung', tag: 'buchung-'.$booking->id, knopf: 'Zum Termin'));
            } else {
                $this->teamMelden($booking->user, 'Verschoben: '.$booking->user->name, ($art?->title ?? 'Sitzung').', neu '.$wann.' (vorher '.Zeit::wann($alt).')');
            }

            return $booking->fresh(['event', 'type']);
        });
    }

    protected function teamMelden(User $person, string $titel, string $text): void
    {
        $m = Membership::where('user_id', $person->id)->first();
        $this->notifier->send($this->chat->teamIds(), new Nachricht(
            titel: $titel, text: $text,
            url: $m ? route('coachees.show', $m) : null,
            anlass: 'system', tag: 'buchung-team-'.$person->id,
        ));
    }
}

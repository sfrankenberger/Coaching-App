<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Event;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Freie Zeiten (wie nvc_freie_zeiten): Im Kalender traegt das Team Bloecke mit einem Stichwort ein
 * (settings.booking.block_keyword, z. B. "Coachingblock"). Steht zusaetzlich der block_tag einer Art
 * im Titel, gilt der Block nur fuer diese Art. Alles andere Belegte sperrt, ebenso gebuchte Sitzungen.
 * Startzeiten im Raster; die Sitzung samt Puffer muss ganz in den Block passen.
 */
class Verfuegbarkeit
{
    public function __construct(protected CurrentTenant $current, protected GoogleCalendar $google) {}

    protected function opt(string $k, mixed $std): mixed
    {
        return $this->current->get()?->setting('booking.'.$k, $std) ?? $std;
    }

    /** @return Collection<int, Carbon> freie Startzeiten, frueheste zuerst */
    public function zeiten(BookingType $art, bool $frisch = false): Collection
    {
        $von = now()->addHours((int) $this->opt('lead_hours', 24));
        $bis = now()->addDays((int) $this->opt('horizon_days', 42))->endOfDay();
        $schluessel = 'buchung-kalender-'.$this->current->id();
        if ($frisch) {
            Cache::forget($schluessel);
        }
        $eintraege = Cache::remember($schluessel, 300, fn () => $this->google->eintraege(now()->startOfDay(), $bis));

        $wort = mb_strtolower((string) $this->opt('block_keyword', 'Coaching'));
        $tags = BookingType::whereNotNull('block_tag')->pluck('block_tag')->map(fn ($t) => mb_strtolower($t))->filter()->values();
        $bloecke = [];
        $belegt = [];
        foreach ($eintraege as $e) {
            $s = mb_strtolower($e['summary']);
            if ($wort !== '' && str_contains($s, $wort)) {
                $eigene = $tags->filter(fn ($t) => str_contains($s, $t));
                if ($eigene->isEmpty() || ($art->block_tag && $eigene->contains(mb_strtolower($art->block_tag)))) {
                    $bloecke[] = $e;
                }

                continue;
            }
            if (! $e['frei']) {
                $belegt[] = [$e['start'], $e['ende']];
            }
        }
        // Schon gebuchte Sitzungen und 1:1-Termine aus der App sperren auch
        foreach (Booking::where('status', 'gebucht')->where('starts_at', '>=', now()->subDay())->get() as $b) {
            $belegt[] = [$b->starts_at, $b->block_ends_at ?? $b->starts_at->copy()->addHour()];
        }
        foreach (Event::where('type', 'one_on_one')->where('is_published', true)->where('starts_at', '>=', now()->subDay())->get() as $ev) {
            $belegt[] = [$ev->starts_at, $ev->ends_at ?? $ev->starts_at->copy()->addHour()];
        }

        $raster = max(5, (int) $this->opt('grid_minutes', 15));
        $dauer = $art->blockMinuten();
        $zeiten = collect();
        foreach ($bloecke as $blk) {
            $t = $blk['start']->copy()->second(0);
            $rest = $t->minute % $raster;
            if ($rest) {
                $t->addMinutes($raster - $rest);
            }
            for (; $t->copy()->addMinutes($dauer)->lte($blk['ende']); $t->addMinutes($raster)) {
                if ($t->lt($von) || $t->gt($bis)) {
                    continue;
                }
                $ende = $t->copy()->addMinutes($dauer);
                $frei = collect($belegt)->every(fn ($b) => $ende->lte($b[0]) || $t->gte($b[1]));
                if ($frei) {
                    $zeiten->push($t->copy()->setTimezone($this->current->get()?->timezone ?: config('app.timezone')));
                }
            }
        }

        return $zeiten->unique(fn (Carbon $c) => $c->getTimestamp())->sortBy(fn (Carbon $c) => $c->getTimestamp())->values();
    }
}

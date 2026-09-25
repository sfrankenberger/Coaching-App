<?php

namespace App\Recordings;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Chat\Chat;
use App\Filament\Coach\Resources\Events\EventResource;
use App\Models\Event;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Wache nach jedem Termin (wie novamira-aufzeichnungen): Aufzeichnung auf Vimeo finden,
 * Abschrift aus der Textspur holen, Zusammenfassung schreiben lassen, dann der Coachin
 * melden oder, wenn eingestellt, gleich freigeben. Laeuft alle 15 Minuten je Mandant.
 *
 * Einstellungen in tenants.settings.recordings: wait_minutes (20), max_tries (24),
 * transcript_tries (12), auto_release_group, auto_release_one_on_one, notify_coach (true).
 */
class Wache
{
    public const OFFEN = [null, 'wartet', 'gefunden', 'abschrift'];

    protected ?array $videos = null;

    public function __construct(
        protected CurrentTenant $current,
        protected Vimeo $vimeo,
        protected Summarizer $summarizer,
        protected Freigabe $freigabe,
        protected Notifier $notifier,
        protected Chat $chat,
    ) {}

    protected function opt(string $key, mixed $standard): mixed
    {
        return $this->current->get()?->setting('recordings.'.$key, $standard) ?? $standard;
    }

    /** Termine, die gerade beobachtet werden. */
    public function kandidaten(): Collection
    {
        $warten = (int) $this->opt('wait_minutes', 20);

        return Event::query()->where('is_published', true)->whereNotIn('type', Event::ALL_DAY_TYPES)
            ->whereNull('recording_notified_at')
            ->where('starts_at', '>=', now()->subDays(3))->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('recording_status')->orWhereIn('recording_status', array_filter(self::OFFEN)))
            ->orderBy('starts_at')->get()
            ->filter(fn (Event $e) => ($e->ends_at ?? $e->starts_at->copy()->addMinutes(90))->copy()->addMinutes($warten)->isPast())
            ->values();
    }

    /** @return array{zugeordnet: int, abschriften: int, zusammenfassungen: int, freigegeben: int, gemeldet: int} */
    public function lauf(): array
    {
        $bericht = ['zugeordnet' => 0, 'abschriften' => 0, 'zusammenfassungen' => 0, 'freigegeben' => 0, 'gemeldet' => 0];
        if (! $this->vimeo->konfiguriert()) {
            return $bericht;
        }
        $kandidaten = $this->kandidaten();

        // 1. Videos zuordnen
        $ohne = $kandidaten->filter(fn (Event $e) => ! $e->hasRecording());
        if ($ohne->isNotEmpty()) {
            $bericht['zugeordnet'] = $this->zuordnen($ohne);
            foreach ($ohne->filter(fn (Event $e) => ! $e->hasRecording()) as $e) {
                $versuche = $e->recording_tries + 1;
                $nichtGefunden = $versuche >= (int) $this->opt('max_tries', 24);
                $e->forceFill(['recording_tries' => $versuche, 'recording_status' => $nichtGefunden ? 'nicht_gefunden' : 'wartet'])->saveQuietly();
                if ($nichtGefunden) {
                    $bericht['gemeldet'] += $this->melden($e, 'Keine Aufzeichnung gefunden: '.$e->title, 'Auf Vimeo ist nach dem Termin nichts aufgetaucht. Trag den Link selbst ein, wenn es eine Aufzeichnung gibt.');
                }
            }
        }

        foreach ($kandidaten->filter(fn (Event $e) => $e->hasRecording()) as $e) {
            $e->refresh();
            // 2. Abschrift
            if (blank($e->transcript)) {
                $id = $e->vimeo_id ?: (preg_match('~vimeo\.com/(?:video/)?(\d+)~', (string) $e->recording_url, $m) ? $m[1] : null);
                $text = null;
                try {
                    $text = $id ? $this->vimeo->abschrift($id) : null;
                } catch (Throwable) {
                }
                if ($text) {
                    $e->forceFill(['transcript' => $text, 'recording_status' => 'abschrift', 'recording_tries' => 0])->saveQuietly();
                    $bericht['abschriften']++;
                } else {
                    $versuche = $e->recording_tries + 1;
                    if (! $id || $versuche >= (int) $this->opt('transcript_tries', 12)) {
                        $e->forceFill(['recording_status' => 'ohne_abschrift', 'recording_tries' => $versuche])->saveQuietly();
                        $bericht['gemeldet'] += $this->melden($e, 'Aufzeichnung ohne Abschrift: '.$e->title, 'Die Aufzeichnung ist am Termin, aber Vimeo liefert keine Abschrift. Schreib eine kurze Zusammenfassung selbst oder gib sie so frei.');
                    } else {
                        $e->forceFill(['recording_status' => 'gefunden', 'recording_tries' => $versuche])->saveQuietly();
                    }

                    continue;
                }
            }
            // 3. Zusammenfassung
            if (blank($e->summary) && Anthropic::configured($this->current->get())) {
                $s = $this->summarizer->event($e);
                $e->refresh();
                if (! $s->isDone()) {
                    $versuche = $e->recording_tries + 1;
                    $e->forceFill(['recording_tries' => $versuche, 'recording_status' => $versuche >= 3 ? 'bereit' : 'abschrift'])->saveQuietly();
                    if ($versuche < 3) {
                        continue;
                    }
                } else {
                    $bericht['zusammenfassungen']++;
                }
            }
            // 4. Bereit: freigeben oder melden
            $e->forceFill(['recording_status' => 'bereit'])->saveQuietly();
            $auto = $e->user_id ? $this->opt('auto_release_one_on_one', false) : $this->opt('auto_release_group', false);
            if ($auto) {
                $this->freigabe->freigeben($e, (array) $this->opt('default_channels', ['mail', 'push']));
                $bericht['freigegeben']++;
            } else {
                $bericht['gemeldet'] += $this->melden($e, 'Aufzeichnung bereit: '.$e->title, 'Link, Abschrift und Zusammenfassung sind am Termin. Schau kurz drüber und gib sie frei.');
            }
        }

        return $bericht;
    }

    /** Videos den Terminen zuordnen: Datum und Zeit im Titel, sonst Upload-Zeit. */
    public function zuordnen(Collection $events): int
    {
        $this->videos ??= $this->vimeo->videos(20);
        $vergeben = Event::whereNotNull('vimeo_id')->pluck('vimeo_id')->all();
        $n = 0;
        foreach ($this->videos as $v) {
            $id = Vimeo::id($v);
            if ($id === '' || in_array($id, $vergeben, true)) {
                continue;
            }
            [$zeit, $fenster] = $this->zeitpunkt($v);
            if (! $zeit) {
                continue;
            }
            $treffer = $events->filter(fn (Event $e) => ! $e->hasRecording() && abs($e->starts_at->getTimestamp() - $zeit->getTimestamp()) <= $fenster)
                ->sortBy(fn (Event $e) => abs($e->starts_at->getTimestamp() - $zeit->getTimestamp()))->first();
            if (! $treffer) {
                continue;
            }
            $treffer->forceFill([
                'recording_url' => $v['link'] ?? null,
                'vimeo_id' => $id,
                'recording_duration' => isset($v['duration']) ? Vimeo::dauer((int) $v['duration']) : null,
                'recording_thumb' => Vimeo::bild($v),
                'recording_status' => 'gefunden',
                'recording_tries' => 0,
            ])->saveQuietly();
            $vergeben[] = $id;
            $n++;
        }

        return $n;
    }

    /** @return array{0: ?Carbon, 1: int} Zeitpunkt und Suchfenster in Sekunden */
    public function zeitpunkt(array $video): array
    {
        $tz = $this->current->get()?->timezone ?: config('app.timezone');
        $name = (string) ($video['name'] ?? '');
        if (preg_match('~(\d{4})-(\d{2})-(\d{2})[ _T](\d{2})[:.](\d{2})~u', $name, $m)) {
            return [Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5], 0, $tz), 4 * 3600];
        }
        if (preg_match('~(\d{1,2})\.(\d{1,2})\.(\d{4})~u', $name, $m)) {
            return [Carbon::create((int) $m[3], (int) $m[2], (int) $m[1], 12, 0, 0, $tz), 18 * 3600];
        }
        $c = $video['created_time'] ?? null;

        return [$c ? Carbon::parse($c) : null, 18 * 3600];
    }

    protected function melden(Event $e, string $titel, string $text): int
    {
        if (! $this->opt('notify_coach', true)) {
            return 0;
        }
        $team = $this->current->get()?->owners()->pluck('users.id') ?? collect();
        if ($team->isEmpty()) {
            $team = $this->chat->teamIds();
        }
        $this->notifier->send($team, new Nachricht(
            titel: $titel,
            text: $text,
            url: EventResource::getUrl('edit', ['record' => $e], panel: 'coach'),
            anlass: 'system',
            tag: 'aufzeichnung-coach-'.$e->id,
            knopf: 'Zum Termin',
        ));

        return 1;
    }
}

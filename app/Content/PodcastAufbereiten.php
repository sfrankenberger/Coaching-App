<?php

namespace App\Content;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Audio\Transkript;
use App\Models\PodcastEpisode;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Neue Podcastfolgen aufbereiten (wie lea-podcast-lauf): Abschrift aus dem Audio ueber den Audio-Dienst
 * des Mandanten, dann Zusammenfassung, Kapitel, FAQ und Schlagworte per KI. Laeuft stuendlich nach den Feeds.
 */
class PodcastAufbereiten
{
    public const VERSUCHE = 3;

    public function __construct(protected CurrentTenant $current, protected Transkript $transkript, protected Summarizer $summarizer) {}

    /** Folgen, denen Abschrift oder Kapitel fehlen und die nicht schon dreimal gescheitert sind. */
    public function offen(): Collection
    {
        return PodcastEpisode::query()->where('is_published', true)->whereNotNull('audio_url')
            ->where(fn ($q) => $q->whereNull('transcript')->orWhereNull('chapters'))
            ->orderByDesc('published_at')->get()
            ->filter(fn (PodcastEpisode $e) => (int) ($e->settings['aufbereitung']['versuche'] ?? 0) < self::VERSUCHE)->values();
    }

    public function lauf(int $grenze = 3): array
    {
        $b = ['abschriften' => 0, 'kapitel' => 0, 'offen' => 0];
        foreach ($this->offen()->take($grenze) as $e) {
            $stand = $this->aufbereiten($e);
            $b['abschriften'] += (int) ($stand['abschrift'] ?? 0);
            $b['kapitel'] += (int) ($stand['kapitel'] ?? 0);
            $b['offen'] += empty($stand['fertig']) ? 1 : 0;
        }

        return $b;
    }

    public function aufbereiten(PodcastEpisode $e): array
    {
        $tenant = $this->current->get();
        $out = ['abschrift' => 0, 'kapitel' => 0, 'fertig' => false];
        try {
            if (blank($e->transcript)) {
                if (! $this->transkript->konfiguriert($tenant)) {
                    return $this->merken($e, 'Kein Audio-Dienst eingerichtet (Verbindungen).') + $out;
                }
                $text = $this->transkript->ausUrl((string) $e->audio_url, true);
                if (blank($text)) {
                    return $this->merken($e, 'Abschrift leer.') + $out;
                }
                $e->forceFill(['transcript' => $text])->saveQuietly();
                $out['abschrift'] = 1;
            }
            if (blank($e->chapters)) {
                if (! Anthropic::configured($tenant)) {
                    return $this->merken($e, 'Kein KI-Schlüssel.') + $out;
                }
                $s = $this->summarizer->episode($e->fresh());
                if (! $s->isDone()) {
                    return $this->merken($e, (string) $s->error) + $out;
                }
                $out['kapitel'] = 1;
            }
        } catch (Throwable $ex) {
            report($ex);

            return $this->merken($e, $ex->getMessage()) + $out;
        }
        $settings = $e->settings ?? [];
        unset($settings['aufbereitung']);
        $e->forceFill(['settings' => $settings])->saveQuietly();
        $out['fertig'] = true;

        return $out;
    }

    protected function merken(PodcastEpisode $e, string $fehler): array
    {
        $a = (array) ($e->settings['aufbereitung'] ?? []);
        $e->forceFill(['settings' => array_merge($e->settings ?? [], ['aufbereitung' => ['versuche' => (int) ($a['versuche'] ?? 0) + 1, 'fehler' => mb_substr($fehler, 0, 300), 'am' => now()->toIso8601String()]])])->saveQuietly();

        return ['fehler' => $fehler];
    }
}

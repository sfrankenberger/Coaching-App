<?php

namespace App\Recordings;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Models\Unit;
use App\Models\UnitVideo;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Lektionsvideos aufbereiten (wie lea-lektion-kapitel): Abschrift aus der Vimeo-Textspur,
 * Zusammenfassung mit Kapiteln per KI. Laeuft mit der Aufzeichnungs-Wache alle 15 Minuten.
 */
class LektionsVideo
{
    use HoltAbschrift;

    public const VERSUCHE = 6;

    public function __construct(protected CurrentTenant $current, protected Vimeo $vimeo, protected Summarizer $summarizer) {}

    /** [Unit, vimeoId] fuer jedes Vimeo-Video einer veroeffentlichten Einheit, dem noch etwas fehlt. */
    public function offen(): Collection
    {
        $out = collect();
        Unit::query()->where('is_published', true)->whereNotNull('videos')->with('videoDaten')->orderByDesc('updated_at')->get()
            ->each(function (Unit $u) use ($out) {
                foreach ($u->videoList() as $v) {
                    $id = Vimeo::nummerAus($v['url'] ?? null);
                    if (! $id) {
                        continue;
                    }
                    $d = $u->videoDaten->firstWhere('vimeo_id', $id);
                    if ($d && (($d->prepare_status && ! in_array($d->prepare_status, ['wartet'], true)) || ($d->transcript && $d->summary))) {
                        continue;
                    }
                    $out->push([$u, $id]);
                }
            });

        return $out;
    }

    public function lauf(int $grenze = 10): array
    {
        $b = ['fertig' => 0, 'wartet' => 0, 'offen' => 0];
        if (! $this->vimeo->konfiguriert()) {
            return $b;
        }
        foreach ($this->offen()->take($grenze) as [$unit, $id]) {
            $stand = $this->aufbereiten($unit, $id);
            $b[$stand === 'bereit' ? 'fertig' : ($stand === 'wartet' ? 'wartet' : 'offen')]++;
        }

        return $b;
    }

    /** Dauer, Abschrift und Zusammenfassung holen. Gibt den neuen prepare_status zurueck. */
    public function aufbereiten(Unit $unit, string $vimeoId, bool $neu = false): string
    {
        $v = UnitVideo::firstOrCreate(['unit_id' => $unit->id, 'vimeo_id' => $vimeoId]);
        $v->setRelation('unit', $unit);

        if (blank($v->duration)) {
            try {
                $daten = $this->vimeo->video($vimeoId);
                if (! empty($daten['duration'])) {
                    $v->forceFill(['duration' => Vimeo::dauer((int) $daten['duration'])])->saveQuietly();
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($neu || blank($v->transcript)) {
            $text = $this->abschriftHolen($vimeoId);
            if (! $text) {
                $versuche = $v->prepare_tries + 1;
                $v->forceFill(['prepare_tries' => $versuche])->saveQuietly();

                return $this->stand($v, $versuche >= self::VERSUCHE ? 'ohne_abschrift' : 'wartet');
            }
            $v->forceFill(['transcript' => $text, 'prepare_tries' => 0])->saveQuietly();
        }

        if (($neu || blank($v->summary)) && Anthropic::configured($this->current->get())) {
            $s = $this->summarizer->unitVideo($v);
            if (! $s->isDone()) {
                $versuche = $v->prepare_tries + 1;
                $v->forceFill(['prepare_tries' => $versuche])->saveQuietly();

                return $this->stand($v, $versuche >= 3 ? 'fehler' : 'wartet');
            }
        }

        return $this->stand($v, 'bereit');
    }

    protected function stand(UnitVideo $v, string $stand): string
    {
        $v->forceFill(['prepare_status' => $stand])->saveQuietly();

        return $stand;
    }
}

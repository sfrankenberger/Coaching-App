<?php

namespace App\Recordings;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Models\Resource;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Material mit Vimeo-Video (wie ressourcen.php im alten Plugin): sobald ein Vimeo-Link am Material
 * steht, kommen Dauer, Vorschaubild, Abschrift aus der Textspur und eine Zusammenfassung mit
 * Abschnitten von selbst. Neue Videos brauchen ein paar Minuten, bis Vimeo die Textspur hat,
 * darum hoechstens sechs Versuche, dann "ohne_abschrift".
 */
class MaterialVideo
{
    public const VERSUCHE = 6;

    public function __construct(protected CurrentTenant $current, protected Vimeo $vimeo, protected Summarizer $summarizer) {}

    public static function vimeoId(Resource $r): ?string
    {
        return $r->vimeo_id ?: Vimeo::nummerAus($r->url) ?: Vimeo::nummerAus($r->file_path) ?: Vimeo::nummerAus($r->body);
    }

    /** Material mit Video, dem noch Abschrift oder Zusammenfassung fehlt. */
    public function offen(): Collection
    {
        return Resource::query()->where(fn ($q) => $q->whereNull('summary')->orWhereNull('transcript'))
            ->where(fn ($q) => $q->whereNull('prepare_status')->orWhere('prepare_status', 'wartet'))
            ->orderByDesc('created_at')->get()->filter(fn (Resource $r) => self::vimeoId($r))->values();
    }

    public function lauf(int $grenze = 10): array
    {
        $b = ['fertig' => 0, 'wartet' => 0, 'offen' => 0];
        if (! $this->vimeo->konfiguriert()) {
            return $b;
        }
        foreach ($this->offen()->take($grenze) as $r) {
            $stand = $this->aufbereiten($r);
            $b[$stand === 'bereit' ? 'fertig' : ($stand === 'wartet' ? 'wartet' : 'offen')]++;
        }

        return $b;
    }

    /** Stammdaten, Abschrift und Zusammenfassung holen. Gibt den neuen prepare_status zurueck. */
    public function aufbereiten(Resource $r, bool $neu = false): string
    {
        $id = self::vimeoId($r);
        if (! $id) {
            return $this->stand($r, 'fehler');
        }
        $r->vimeo_id = $id;

        try {
            $v = $this->vimeo->video($id);
            if ($r->type === 'link' || $r->type === 'pdf' && ! $r->file_path) {
                $r->type = 'video';
            }
            if (blank($r->duration) && ! empty($v['duration'])) {
                $r->duration = Vimeo::dauer((int) $v['duration']);
            }
            if (blank($r->image_url) && ($bild = Vimeo::bild($v))) {
                $r->image_url = $bild;
            }
        } catch (Throwable $e) {
            report($e);
        }
        $r->saveQuietly();

        if ($neu || blank($r->transcript)) {
            $text = null;
            try {
                $text = $this->vimeo->abschrift($id);
            } catch (Throwable $e) {
                report($e);
            }
            if (! $text) {
                $versuche = $r->prepare_tries + 1;
                $r->forceFill(['prepare_tries' => $versuche])->saveQuietly();

                return $this->stand($r, $versuche >= self::VERSUCHE ? 'ohne_abschrift' : 'wartet');
            }
            $r->forceFill(['transcript' => $text, 'prepare_tries' => 0])->saveQuietly();
        }

        if (($neu || blank($r->summary)) && Anthropic::configured($this->current->get())) {
            $s = $this->summarizer->resource($r);
            if (! $s->isDone()) {
                $versuche = $r->prepare_tries + 1;
                $r->forceFill(['prepare_tries' => $versuche])->saveQuietly();

                return $this->stand($r, $versuche >= 3 ? 'fehler' : 'wartet');
            }
        }

        return $this->stand($r, 'bereit');
    }

    protected function stand(Resource $r, string $stand): string
    {
        $r->forceFill(['prepare_status' => $stand])->saveQuietly();

        return $stand;
    }
}

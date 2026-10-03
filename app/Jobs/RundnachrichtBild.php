<?php

namespace App\Jobs;

use App\Ai\Impulsbild;
use App\Models\Rundnachricht;
use App\Models\Tenant;
use App\Support\Bildkarte;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bild mit Illustration fuer einen Rundnachricht-Entwurf bauen (dauert mit OpenAI etwa eine Minute, darum im Hintergrund).
 * Das fertige Bild kommt als erster Baustein in den Entwurf, die Seite fragt den Stand ueber den Cache ab.
 */
class RundnachrichtBild implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 400;

    public int $tries = 1;

    public function __construct(public int $tenantId, public int $rundnachrichtId, public string $satz, public ?string $motiv, public ?string $kennung) {}

    public static function schluessel(int $id): string
    {
        return 'rundnachricht-bild:'.$id;
    }

    public function handle(CurrentTenant $current, Impulsbild $bilder): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () use ($bilder) {
            $e = Rundnachricht::find($this->rundnachrichtId);
            if (! $e) {
                return;
            }
            try {
                $motiv = trim((string) $this->motiv) ?: $bilder->motiv((string) $e->titel, (string) $e->text);
                $png = Bildkarte::impuls($this->satz, $e->titel, $this->kennung, $bilder->grafik($motiv));
                $pfad = Bildkarte::speichern($png);
                $block = ['type' => 'bild', 'data' => ['datei' => $pfad, 'url' => null, 'link' => null, 'alt' => $this->satz, 'breite' => 'voll']];
                $e->bloecke = array_merge([$block], array_values((array) $e->bloecke));
                $e->save();
                Cache::put(self::schluessel($e->id), ['stand' => 'fertig', 'satz' => $this->satz, 'motiv' => $motiv], now()->addHour());
            } catch (Throwable $t) {
                report($t);
                Cache::put(self::schluessel($e->id), ['stand' => 'fehler', 'text' => Str::limit($t->getMessage(), 200)], now()->addHour());
            }
        });
    }
}

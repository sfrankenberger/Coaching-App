<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Oeffentliche Angebote fuer die Website: Titel, Kurztext, Bild, Preise, Kauflink. Ohne Anmeldung,
 * fuenf Minuten gecacht, mit CORS, damit auch ein Script auf der Website sie holen kann.
 */
class AngeboteController extends Controller
{
    public function index(Request $request, CurrentTenant $current): JsonResponse
    {
        $tenant = $current->getOrFail();
        $liste = Cache::remember('angebote:'.$tenant->id, now()->addMinutes(5), fn () => Offer::with('programs:id,title')->where('is_active', true)->orderBy('title')->get()
            ->filter(fn (Offer $o) => $o->sichtbar() && $o->kaufbar())->map(fn (Offer $o) => $this->daten($o))->values()->all());

        return $this->antwort(['angebote' => $liste]);
    }

    public function show(Request $request, CurrentTenant $current, string $slug): JsonResponse
    {
        $tenant = $current->getOrFail();
        $daten = Cache::remember('angebot:'.$tenant->id.':'.$slug, now()->addMinutes(5), function () use ($slug) {
            $o = Offer::with('programs:id,title')->where('slug', $slug)->where('is_active', true)->first();

            return $o && $o->kaufbar() ? $this->daten($o) : null;
        });
        abort_unless($daten, 404);

        return $this->antwort($daten);
    }

    protected function daten(Offer $o): array
    {
        return [
            'slug' => $o->slug,
            'titel' => $o->title,
            'art' => $o->type,
            'teaser' => $o->settings['teaser'] ?? null,
            'bild' => $o->settings['bild_url'] ?? null,
            'gratis' => (bool) $o->is_free,
            'preise' => $o->preise(),
            'preise_regulaer' => $o->aktionLaeuft() ? array_filter(['CHF' => $o->preisRegulaer('CHF'), 'EUR' => $o->preisRegulaer('EUR')]) : [],
            'aktion_bis' => $o->aktionLaeuft() ? $o->settings['aktion_bis'] : null,
            'rechnung' => $o->kaufAufRechnung(),
            'zugang_tage' => $o->access_days,
            'programme' => $o->programs->pluck('title')->values()->all(),
            'kaufen' => $o->kaufUrl(),
        ];
    }

    protected function antwort(array $daten): JsonResponse
    {
        return response()->json($daten, 200, ['Access-Control-Allow-Origin' => '*', 'Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

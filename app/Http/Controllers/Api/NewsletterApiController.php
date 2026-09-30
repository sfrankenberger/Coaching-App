<?php

namespace App\Http\Controllers\Api;

use App\Ai\Werkzeuge\NewsletterAnlegen;
use App\Ai\Werkzeuge\NewsletterSenden;
use App\Http\Controllers\Controller;
use App\Models\Kontakt;
use App\Newsletter\Kontakte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Newsletter von aussen anlegen und senden (Website: beim Veroeffentlichen eines Beitrags waehlt die Coachin die
 * Empfaengerinnen). Sanctum-Token mit Faehigkeit mcp, nur Team. Nutzt dieselben Werkzeuge wie der Assistent.
 */
class NewsletterApiController extends Controller
{
    protected function pruefen(Request $request): void
    {
        $u = $request->user();
        abort_unless($u && $u->canManageCurrentTenant() && $u->tokenCan('mcp'), 403, 'Dieser Schlüssel darf keine Newsletter anlegen.');
    }

    /** Tags mit Anzahl bestaetigter Kontakte, fuer die Auswahl auf der Website. */
    public function tags(Request $request, Kontakte $kontakte): JsonResponse
    {
        $this->pruefen($request);
        $alle = $kontakte->alleTags();
        $bestaetigt = Kontakt::bestaetigt()->count();

        return response()->json(['bestaetigt' => $bestaetigt, 'tags' => collect($alle)->map(fn ($n, $t) => ['tag' => $t, 'anzahl' => Kontakt::bestaetigt()->mitTags([$t])->count()])->values()->all()]);
    }

    /** Newsletter anlegen, auf Wunsch gleich senden. */
    public function store(Request $request, NewsletterAnlegen $anlegen, NewsletterSenden $senden): JsonResponse
    {
        $this->pruefen($request);
        $data = $request->validate([
            'betreff' => ['required', 'string', 'max:150'],
            'titel' => ['nullable', 'string', 'max:150'],
            'text' => ['required', 'string', 'max:20000'],
            'vorschautext' => ['nullable', 'string', 'max:150'],
            'bild_url' => ['nullable', 'url', 'max:500'],
            'knopf_text' => ['nullable', 'string', 'max:80'],
            'knopf_url' => ['nullable', 'url', 'max:500'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'senden' => ['nullable', 'boolean'],
            'quelle' => ['nullable', 'string', 'max:120'],
        ]);
        $r = $anlegen->ausfuehren($data, $request->user());
        if ($data['senden'] ?? false) {
            $r = $senden->ausfuehren(['newsletter_id' => $r['newsletter_id'], 'bestaetigt' => true], $request->user()) + $r;
        }

        return response()->json($r, 201);
    }
}

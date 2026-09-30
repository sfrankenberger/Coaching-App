<?php

namespace App\Http\Controllers;

use App\Ai\Anthropic;
use App\Ai\Assistent;
use App\Ai\Dialog;
use App\Ai\Werkzeuge\Werkzeugkasten;
use App\Models\Wissen;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Der Assistent in der App-Huelle: Auskunft ("Frag mich etwas zu deinem Betrieb"), Wissen merken
 * (Second Brain), Verbindung fuer Claude und ChatGPT (MCP). Nur fuer das Team.
 */
class AssistentController extends Controller
{
    public function index(Request $request, CurrentTenant $current): View
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $q = trim((string) $request->query('wissen', ''));

        return view('assistent.index', [
            'beispiele' => Assistent::VORSCHLAEGE,
            'ki' => Anthropic::configured($current->get()),
            'wissen' => Wissen::query()->when($q !== '', fn ($w) => $w->suche($q))->latest()->limit($q !== '' ? 30 : 8)->get(),
            'wissenAnzahl' => Wissen::count(),
            'q' => $q,
            'mcpUrl' => url('/api/mcp'),
            'chat' => $request->session()->get(AssistentChatController::SCHLUESSEL, Dialog::leer()),
            'werkzeuge' => app(Werkzeugkasten::class)->alle(),
        ]);
    }

    /** Etwas merken: ein Gedanke, eine Regel, ein Fakt, den der Assistent spaeter kennt. */
    public function merken(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:200'], 'body' => ['required', 'string', 'max:20000'], 'tags' => ['nullable', 'string', 'max:300']]);
        Wissen::merken($request->user(), $data['body'], $data['title'] ?? null, $data['tags'] ?? null, 'app');

        return redirect()->route('assistent')->with('meldung', 'Gemerkt.');
    }

    public function vergessen(Request $request, Wissen $wissen): RedirectResponse
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $wissen->delete();

        return redirect()->route('assistent')->with('meldung', 'Vergessen.');
    }
}

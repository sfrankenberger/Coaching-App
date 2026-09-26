<?php

namespace App\Http\Controllers;

use App\Content\Inhalte;
use App\Models\Tool;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Werkzeuge fuer die Coach-Ausbildung: Liste und Einzelseite. Ohne Kennzeichen eine freundliche Tuer. */
class WerkzeugeController extends Controller
{
    public function __construct(protected Inhalte $inhalte) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $darf = Tool::darf($user);
        $q = Tool::query()->with('topics')->orderBy('position')->orderBy('title');
        if (! $user->canManageCurrentTenant()) {
            $q->where('is_published', true);
        }

        return view('werkzeuge.index', [
            'darf' => $darf,
            'werkzeuge' => $darf ? $q->get() : collect(),
            'gemerkt' => $this->inhalte->bookmarkKeys($user),
            'tuerUrl' => app(CurrentTenant::class)->get()?->setting('ausbildung_url') ?: app(CurrentTenant::class)->get()?->setting('website'),
        ]);
    }

    public function show(Request $request, Tool $tool): View
    {
        $user = $request->user();
        abort_unless(Tool::darf($user) && ($tool->is_published || $user->canManageCurrentTenant()), 403, 'Dieses Werkzeug gehört zur Coach-Ausbildung.');

        return view('werkzeuge.show', [
            'werkzeug' => $tool,
            'felder' => collect(Tool::FELDER)->filter(fn ($f, $key) => filled($tool->{$key})),
            'gemerkt' => $this->inhalte->bookmarkKeys($user),
        ]);
    }
}

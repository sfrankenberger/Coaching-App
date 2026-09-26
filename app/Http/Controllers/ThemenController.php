<?php

namespace App\Http\Controllers;

use App\Content\Inhalte;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Themenfinder: ein Thema, alle Inhalte dazu. */
class ThemenController extends Controller
{
    public function __construct(protected Inhalte $inhalte) {}

    public function index(Request $request): View
    {
        $suche = mb_strtolower(trim((string) $request->query('q', '')));
        $themen = $this->inhalte->topicsFor($request->user());
        if ($suche !== '') {
            $themen = $themen->filter(fn (Topic $t) => str_contains(mb_strtolower($t->name.' '.$t->description), $suche));
        }

        return view('themen.index', ['themen' => $themen, 'suche' => $suche]);
    }

    public function show(Request $request, Topic $thema): View
    {
        abort_unless($thema->is_visible || $request->user()->canManageCurrentTenant(), 404);
        $zeilen = $this->inhalte->itemsForTopic($thema, $request->user());

        return view('themen.show', [
            'thema' => $thema,
            'gruppen' => $zeilen->groupBy('art')->sortKeysUsing(fn ($a, $b) => array_search($a, array_keys(Inhalte::ARTEN)) <=> array_search($b, array_keys(Inhalte::ARTEN))),
            'gemerkt' => $this->inhalte->bookmarkKeys($request->user()),
        ]);
    }
}

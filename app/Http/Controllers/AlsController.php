<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AlsAndere;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ansehen als: Liste der Personen im Mandanten, umschalten, zurueck. Nur Plattform-Admin. */
class AlsController extends Controller
{
    public function index(Request $request): View
    {
        $echt = AlsAndere::echt($request) ?? $request->user();
        abort_unless($echt->is_platform_admin, 403);
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $liste = Membership::query()->where('status', 'active')->with('user')->get()->filter(fn ($m) => $m->user)
            ->when($q !== '', fn ($c) => $c->filter(fn ($m) => str_contains(mb_strtolower($m->user->name.' '.$m->user->email), $q)))
            ->sortBy([fn ($a, $b) => ($b->role->canManage() <=> $a->role->canManage()) ?: strcasecmp($a->user->name, $b->user->name)])->values();

        return view('als', [
            'echt' => $echt,
            'als' => AlsAndere::echt($request) ? $request->user() : null,
            'team' => $liste->filter(fn ($m) => $m->role->canManage()),
            'personen' => $liste->reject(fn ($m) => $m->role->canManage()),
            'q' => $q,
        ]);
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        $echt = AlsAndere::echt($request) ?? $request->user();
        abort_unless($echt->is_platform_admin, 403);
        abort_unless($user->hasAccessTo(), 404);
        $request->session()->put(AlsAndere::SCHLUESSEL, $user->id);

        return redirect()->route('home')->with('meldung', 'Du siehst die App jetzt als '.$user->name.'.');
    }

    public function ende(Request $request): RedirectResponse
    {
        $request->session()->forget(AlsAndere::SCHLUESSEL);

        return redirect()->route('als')->with('meldung', 'Zurück als du selbst.');
    }
}

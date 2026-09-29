<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Was es gibt": sichtbare Angebote in der App, mit Kaufknopf. */
class AngeboteController extends Controller
{
    public function index(Request $request): View
    {
        $meine = $request->user()->entitlements()->where('status', 'active')->pluck('offer_id')->all();
        $angebote = Offer::with('programs')->where('is_active', true)->orderBy('title')->get()->filter(fn (Offer $o) => $o->sichtbar() && $o->kaufbar());

        return view('angebote.index', ['angebote' => $angebote, 'meine' => $meine]);
    }
}

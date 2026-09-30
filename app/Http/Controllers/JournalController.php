<?php

namespace App\Http\Controllers;

use App\Models\Projekt;
use App\Models\Task;
use App\Programs\Zeitleiste;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mein Journal: die Zeitleiste aus Aufgaben, Notizen, Reflexionen, Begleitung, Terminen und Aufzeichnungen. */
class JournalController extends Controller
{
    public function index(Request $request, Zeitleiste $zeitleiste): View
    {
        $user = $request->user();
        $art = array_key_exists((string) $request->query('art'), Zeitleiste::ARTEN) ? (string) $request->query('art') : null;
        $projekte = Projekt::where('user_id', $user->id)->orderBy('position')->orderBy('name')->get(['id', 'name', 'farbe', 'icon']);
        $projekt = $request->query('projekt') ? $projekte->firstWhere('id', (int) $request->query('projekt')) : null;

        return view('journal.index', [
            'punkte' => $zeitleiste->punkte($user, $art, $projekt),
            'naechster' => $zeitleiste->naechster($user),
            'art' => $art,
            'projekt' => $projekt,
            'projekte' => $projekte,
            'offeneAufgaben' => Task::where('user_id', $user->id)->open()->count(),
        ]);
    }
}

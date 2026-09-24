<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\Note;
use App\Models\Reflection;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mein Journal: Einstieg zu Aufgaben, Notizen, Reflexionen und alten Eintraegen. */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('journal.index', [
            'offeneAufgaben' => Task::where('user_id', $user->id)->open()->count(),
            'notizen' => Note::where('user_id', $user->id)->count(),
            'reflexionen' => Reflection::where('user_id', $user->id)->count(),
            'letzteReflexion' => Reflection::where('user_id', $user->id)->latest()->first(),
            'eintraege' => JournalEntry::where('user_id', $user->id)->latest()->limit(20)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Coach\Kommentare;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Kommentare unter Reflexion, Notiz, Aufgabe und Uebungsantwort. */
class KommentarController extends Controller
{
    public function __construct(protected Kommentare $kommentare) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'typ' => ['required', 'in:'.implode(',', array_keys(Kommentare::TYPEN))],
            'id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $item = $this->kommentare->finden($data['typ'], (int) $data['id']);
        abort_unless($item, 404);
        $this->kommentare->schreiben($request->user(), $item, $data['body']);

        return back()->with('meldung', 'Kommentar gespeichert.');
    }

    public function destroy(Request $request, Comment $kommentar): RedirectResponse
    {
        abort_if($kommentar->commentable_type === 'question', 404);
        Gate::authorize('delete', $kommentar);
        $kommentar->delete();

        return back();
    }
}

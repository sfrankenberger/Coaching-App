<?php

namespace App\Http\Controllers;

use App\Content\Inhalte;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Merkliste: alles, was sich die Person gemerkt hat, quer ueber die Inhalte. */
class MerklisteController extends Controller
{
    public function __construct(protected Inhalte $inhalte) {}

    public function index(Request $request): View
    {
        $zeilen = $this->inhalte->bookmarksFor($request->user());

        return view('merkliste.index', [
            'zeilen' => $zeilen,
            'gemerkt' => $this->inhalte->bookmarkKeys($request->user()),
        ]);
    }
}

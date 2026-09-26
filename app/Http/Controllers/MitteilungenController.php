<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Die Glocke: alle Mitteilungen der Person, neueste zuerst. Aufrufen markiert als gelesen. */
class MitteilungenController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $liste = $user->notifications()->limit(60)->get();
        $neu = $liste->whereNull('read_at')->pluck('id');
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return view('mitteilungen', ['liste' => $liste, 'neu' => $neu->flip()]);
    }

    public function oeffnen(Request $request, string $id): RedirectResponse
    {
        $m = $request->user()->notifications()->findOrFail($id);
        $m->markAsRead();

        return redirect()->to($m->url() ?: route('mitteilungen'));
    }
}

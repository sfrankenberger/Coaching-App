<?php

namespace App\Http\Controllers;

use App\Coach\Ansicht;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Umschalten zwischen Arbeitsplatz und "wie eine Teilnehmerin" (nur Team). */
class AnsichtController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        Ansicht::setzen($request->user(), (string) $request->input('ansicht'));

        return redirect()->route('home');
    }
}

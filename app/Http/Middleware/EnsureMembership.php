<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Angemeldet reicht nicht: die Person braucht eine aktive Mitgliedschaft im
 * Mandanten dieser Domain (oder ist Plattform-Admin). Sonst wird sie hier
 * abgemeldet, ohne dass die Sitzung auf einer anderen Domain leidet.
 */
class EnsureMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAccessTo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('anmelden')->with('fehler', 'Für diesen Bereich hast du keinen Zugang.');
        }

        return $next($request);
    }
}

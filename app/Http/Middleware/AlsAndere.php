<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Ansehen als": ein Plattform-Admin sieht die App genau so wie eine andere Person (Lea, das Team,
 * jede Teilnehmerin). Die Sitzung gehoert weiter dem Admin, nur der Request laeuft als die andere
 * Person. Alles, was er dabei tut, geschieht in ihrem Namen, darum steht der Balken oben.
 */
class AlsAndere
{
    public const SCHLUESSEL = 'als_user_id';

    public function handle(Request $request, Closure $next): Response
    {
        $alsId = (int) $request->session()->get(self::SCHLUESSEL, 0);
        if ($alsId && ($echt = Auth::user()) && $echt->is_platform_admin && $echt->id !== $alsId) {
            $andere = User::find($alsId);
            if ($andere && $andere->hasAccessTo()) {
                $request->attributes->set('als_echt', $echt);
                Auth::guard('web')->setUser($andere);
            } else {
                $request->session()->forget(self::SCHLUESSEL);
            }
        }

        return $next($request);
    }

    /** Der echte Admin hinter der Verkleidung, sonst null. */
    public static function echt(?Request $request = null): ?User
    {
        return ($request ?? request())->attributes->get('als_echt');
    }
}

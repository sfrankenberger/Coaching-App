<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Bridge;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** GET /sso?token=... aus dem alten Mitgliederbereich. */
class BridgeController extends Controller
{
    public function __invoke(Request $request, Bridge $bridge, CurrentTenant $current): RedirectResponse
    {
        $key = 'bridge:'.$current->id().':'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return redirect()->route('anmelden')->with('fehler', 'Zu viele Versuche. Bitte in ein paar Minuten nochmals.');
        }
        RateLimiter::hit($key, 600);

        $result = $bridge->consume((string) $request->query('token', ''));
        if (! $result) {
            return redirect()->route('anmelden')->with('fehler', 'Der Link ist abgelaufen. Melde dich einfach hier an, das geht genauso schnell.');
        }
        [$user, $weiter] = $result;
        RateLimiter::clear($key);
        LoginController::loginAs($request, $user);

        return redirect()->to($weiter ?: route('home'));
    }
}

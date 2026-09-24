<?php

namespace App\Http\Controllers\Auth;

use App\Auth\MagicLink;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google und Apple ueber Socialite. Die Zugangsdaten stehen je Mandant in
 * tenants.settings unter oauth.google bzw. oauth.apple. Ohne Zugangsdaten
 * erscheint der Knopf nicht.
 */
class SocialController extends Controller
{
    public const PROVIDERS = ['google' => 'Google', 'apple' => 'Apple'];

    public function __construct(protected CurrentTenant $current) {}

    /** Dienste, fuer die der Mandant Zugangsdaten hinterlegt hat. */
    public static function availableProviders(Tenant $tenant): array
    {
        $out = [];
        foreach (self::PROVIDERS as $key => $label) {
            $cfg = $tenant->setting("oauth.$key");
            if (is_array($cfg) && filled($cfg['client_id'] ?? null) && filled($cfg['client_secret'] ?? null)) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    public function redirect(Request $request, string $dienst): Response
    {
        $this->configure($dienst);

        $request->session()->put('anmelden.weiter', MagicLink::cleanWeiter($request->query('weiter')));

        $driver = Socialite::driver($dienst);
        if ($dienst === 'apple') {
            $driver->scopes(['name', 'email']);
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $dienst): Response
    {
        $this->configure($dienst);

        try {
            $social = Socialite::driver($dienst)->user();
        } catch (\Throwable) {
            return redirect()->route('anmelden')->with('fehler', 'Die Anmeldung über '.self::PROVIDERS[$dienst].' hat nicht geklappt. Versuch es nochmals oder lass dir einen Link schicken.');
        }

        $email = Str::lower(trim((string) $social->getEmail()));
        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user || ! $user->hasAccessTo()) {
            return response()->view('auth.kein-zugang', ['email' => $email ?: '(keine E-Mail-Adresse erhalten)'], 403);
        }

        LoginController::loginAs($request, $user);

        return redirect()->to($request->session()->pull('anmelden.weiter') ?: route('home'));
    }

    protected function configure(string $dienst): void
    {
        abort_unless(array_key_exists($dienst, self::PROVIDERS), 404);

        $tenant = $this->current->getOrFail();
        $cfg = $tenant->setting("oauth.$dienst");
        abort_unless(is_array($cfg) && filled($cfg['client_id'] ?? null), 404);

        config(["services.$dienst" => [
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'] ?? null,
            'redirect' => route('anmelden.dienst.zurueck', ['dienst' => $dienst]),
        ]]);
    }
}

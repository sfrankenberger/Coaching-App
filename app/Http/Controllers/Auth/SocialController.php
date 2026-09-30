<?php

namespace App\Http\Controllers\Auth;

use App\Auth\MagicLink;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google und Apple ueber Socialite. Die Zugangsdaten stehen je Mandant in
 * tenants.settings unter oauth.google bzw. oauth.apple. Ohne Zugangsdaten
 * erscheint der Knopf nicht.
 *
 * Wer sich einmal ueber einen Dienst angemeldet hat, ist damit verknuepft
 * (social_accounts): danach zaehlt die Kennung beim Dienst, nicht die Mailadresse.
 * So klappt es auch, wenn jemand bei Apple die Adresse verbirgt. Im Profil
 * lassen sich Dienste verknuepfen und trennen.
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
            if (self::configured($tenant, $key)) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    /** Google braucht Client-ID und Secret; Apple die Services-ID und entweder ein fertiges Secret oder Team-ID, Key-ID und den .p8-Schluessel. */
    public static function configured(Tenant $tenant, string $dienst): bool
    {
        $cfg = $tenant->setting("oauth.$dienst");
        if (! is_array($cfg) || ! filled($cfg['client_id'] ?? null)) {
            return false;
        }
        if (filled($cfg['client_secret'] ?? null)) {
            return true;
        }

        return $dienst === 'apple' && filled($cfg['team_id'] ?? null) && filled($cfg['key_id'] ?? null) && filled($cfg['private_key'] ?? null);
    }

    public function redirect(Request $request, string $dienst): Response
    {
        $this->configure($dienst);

        $request->session()->put('anmelden.weiter', MagicLink::cleanWeiter($request->query('weiter')));
        $request->session()->forget('dienst.verknuepfen');

        return $this->zumDienst($dienst);
    }

    /** Aus dem Profil: den Dienst mit dem angemeldeten Konto verknuepfen. */
    public function verknuepfen(Request $request, string $dienst): Response
    {
        $this->configure($dienst);
        $request->session()->put('dienst.verknuepfen', $request->user()->id);

        return $this->zumDienst($dienst);
    }

    public function trennen(Request $request, string $dienst): RedirectResponse
    {
        abort_unless(array_key_exists($dienst, self::PROVIDERS), 404);
        $request->user()->socialAccounts()->where('provider', $dienst)->delete();

        return redirect()->to(route('profil').'#anmelden')->with('meldung', self::PROVIDERS[$dienst].' ist getrennt. Anmelden geht weiter mit Link, Passkey oder Passwort.');
    }

    public function callback(Request $request, string $dienst): Response
    {
        $this->configure($dienst);
        $verknuepfen = (int) $request->session()->pull('dienst.verknuepfen');

        try {
            $social = Socialite::driver($dienst)->user();
        } catch (\Throwable $e) {
            report($e);
            $fehler = 'Die Anmeldung über '.self::PROVIDERS[$dienst].' hat nicht geklappt. Versuch es nochmals oder lass dir einen Link schicken.';

            return $verknuepfen && $request->user()
                ? redirect()->to(route('profil').'#anmelden')->with('fehler', $fehler)
                : redirect()->route('anmelden')->with('fehler', $fehler);
        }

        $kennung = (string) $social->getId();
        $email = Str::lower(trim((string) $social->getEmail()));
        $name = trim((string) $social->getName()) ?: null;

        // Aus dem Profil: an das angemeldete Konto haengen.
        if ($verknuepfen && $request->user() && $request->user()->id === $verknuepfen) {
            $fremd = SocialAccount::where('provider', $dienst)->where('provider_id', $kennung)->where('user_id', '!=', $verknuepfen)->exists();
            if ($fremd) {
                return redirect()->to(route('profil').'#anmelden')->with('fehler', 'Dieses '.self::PROVIDERS[$dienst].'-Konto gehört schon zu einer anderen Person.');
            }
            $this->merken($request->user(), $dienst, $kennung, $email, $name);

            return redirect()->to(route('profil').'#anmelden')->with('meldung', self::PROVIDERS[$dienst].' ist verknüpft. Ab jetzt reicht ein Tipp auf den Knopf.');
        }

        $user = SocialAccount::where('provider', $dienst)->where('provider_id', $kennung)->first()?->user;
        $user ??= $email && ! SocialAccount::versteckt($email) ? User::where('email', $email)->first() : null;

        if (! $user || ! $user->hasAccessTo()) {
            if ($dienst === 'apple' && (SocialAccount::versteckt($email) || $email === '')) {
                return redirect()->route('anmelden')->with('fehler', 'Du hast deine Adresse bei Apple verborgen, darum können wir dich nicht zuordnen. Lass dir einmal einen Link an deine Mailadresse schicken und verknüpfe Apple danach im Profil. Ab dann klappt es direkt.');
            }

            return response()->view('auth.kein-zugang', ['email' => $email ?: '(keine E-Mail-Adresse erhalten)'], 403);
        }

        $this->merken($user, $dienst, $kennung, $email, $name);
        LoginController::loginAs($request, $user);

        return redirect()->to($request->session()->pull('anmelden.weiter') ?: route('home'));
    }

    protected function merken(User $user, string $dienst, string $kennung, ?string $email, ?string $name): void
    {
        if ($kennung === '') {
            return;
        }
        SocialAccount::updateOrCreate(
            ['provider' => $dienst, 'provider_id' => $kennung],
            ['user_id' => $user->id, 'email' => $email ?: null, 'name' => $name],
        );
    }

    protected function zumDienst(string $dienst): Response
    {
        $driver = Socialite::driver($dienst);
        if ($dienst === 'apple') {
            $driver->scopes(['name', 'email']);
        }

        return $driver->redirect();
    }

    protected function configure(string $dienst): void
    {
        abort_unless(array_key_exists($dienst, self::PROVIDERS), 404);

        $tenant = $this->current->getOrFail();
        abort_unless(self::configured($tenant, $dienst), 404);
        $cfg = $tenant->setting("oauth.$dienst");

        $services = [
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'] ?? null,
            'redirect' => route('anmelden.dienst.zurueck', ['dienst' => $dienst]),
        ];
        if ($dienst === 'apple') {
            // Der Provider erzeugt das Client-Secret (JWT) selbst aus Team-ID, Key-ID und dem .p8-Schluessel; es gilt sechs Monate, darum nie von Hand pflegen.
            $services += ['team_id' => $cfg['team_id'] ?? null, 'key_id' => $cfg['key_id'] ?? null, 'private_key' => $cfg['private_key'] ?? null];
            if (filled($services['private_key'])) {
                $services['client_secret'] = null;
            }
        }
        config(["services.$dienst" => $services]);
    }
}

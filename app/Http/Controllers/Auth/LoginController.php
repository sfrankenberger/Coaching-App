<?php

namespace App\Http\Controllers\Auth;

use App\Auth\MagicLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(protected MagicLink $magicLink, protected CurrentTenant $current) {}

    public function form(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('home'));
        }

        return view('auth.anmelden', [
            'weiter' => MagicLink::cleanWeiter($request->query('weiter')),
            'dienste' => SocialController::availableProviders($this->current->getOrFail()),
        ]);
    }

    /** Magic Link anfordern. */
    public function sendLink(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'weiter' => ['nullable', 'string', 'max:500'],
        ]);

        $email = Str::lower(trim($data['email']));
        $key = 'magic-link:'.$this->current->id().':'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput()->withErrors(['email' => 'Zu viele Versuche. Bitte in ein paar Minuten nochmals.']);
        }
        RateLimiter::hit($key, 600);

        $this->magicLink->send($email, $data['weiter'] ?? null, $request->ip());
        $request->session()->put('anmelden.email', $email);

        return view('auth.link-geschickt', ['email' => $email, 'minuten' => MagicLink::MINUTEN, 'weiter' => MagicLink::cleanWeiter($data['weiter'] ?? null)]);
    }

    /**
     * Code aus der Mail einloesen. Fuer die App auf dem Handy: der Link aus der Mail oeffnet dort den Browser,
     * der Code laesst sich in der installierten App eintippen.
     */
    public function code(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'code' => ['required', 'string', 'max:12'],
            'weiter' => ['nullable', 'string', 'max:500'],
        ]);
        $email = Str::lower(trim((string) ($data['email'] ?: $request->session()->get('anmelden.email', ''))));
        if ($email === '') {
            return redirect()->route('anmelden')->with('fehler', 'Fordere zuerst einen Code an.');
        }

        $key = 'anmelden-code:'.$this->current->id().':'.sha1($email.'|'.$request->ip());
        $zurueck = fn (string $fehler) => redirect()->route('anmelden', array_filter(['weiter' => $data['weiter'] ?? null]))->withInput(['email' => $email, 'code_offen' => 1])->withErrors(['code' => $fehler]);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return $zurueck('Zu viele Versuche. Fordere in ein paar Minuten einen neuen Code an.');
        }
        RateLimiter::hit($key, 600);

        $token = $this->magicLink->consumeCode($email, $data['code']);
        if (! $token || ! $token->user?->hasAccessTo()) {
            return $zurueck('Dieser Code passt nicht oder ist abgelaufen. Schau nochmals in die Mail oder fordere einen neuen an.');
        }

        RateLimiter::clear($key);
        $request->session()->forget('anmelden.email');
        $this->loginAs($request, $token->user);

        return redirect()->to($token->weiter ?: MagicLink::cleanWeiter($data['weiter'] ?? null) ?: route('home'));
    }

    /** Magic Link einloesen. */
    public function token(Request $request, string $token): RedirectResponse
    {
        $loginToken = $this->magicLink->consume($token);

        if (! $loginToken || ! $loginToken->user?->hasAccessTo()) {
            return redirect()->route('anmelden')->with('fehler', 'Dieser Link ist abgelaufen oder wurde schon benutzt. Fordere einfach einen neuen an.');
        }

        $this->loginAs($request, $loginToken->user);

        return redirect()->to($loginToken->weiter ?: route('home'));
    }

    /** Passwort ist freiwillig. Wer eines hat, darf es benutzen. */
    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
            'weiter' => ['nullable', 'string', 'max:500'],
        ]);

        $key = 'passwort:'.$this->current->id().':'.sha1(Str::lower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput()->withErrors(['password' => 'Zu viele Versuche. Bitte in ein paar Minuten nochmals.']);
        }
        RateLimiter::hit($key, 600);

        $user = User::where('email', Str::lower(trim($data['email'])))->first();

        if (! $user || ! $user->hasPassword() || ! Hash::check($data['password'], $user->password) || ! $user->hasAccessTo()) {
            return back()->withInput($request->only('email'))->withErrors(['password' => 'Das passt nicht zusammen. Versuch es nochmals oder lass dir einen Link schicken.']);
        }

        RateLimiter::clear($key);
        $this->loginAs($request, $user);

        return redirect()->to(MagicLink::cleanWeiter($data['weiter'] ?? null) ?: route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('anmelden')->with('meldung', 'Du bist abgemeldet. Bis bald.');
    }

    public static function loginAs(Request $request, User $user): void
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}

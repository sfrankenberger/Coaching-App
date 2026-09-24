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

        return view('auth.link-geschickt', ['email' => $email, 'minuten' => MagicLink::MINUTEN]);
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

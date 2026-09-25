<?php

namespace App\Http\Controllers\Auth;

use App\Auth\MagicLink;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

/**
 * Passkeys (WebAuthn): im Profil anlegen, auf /anmelden benutzen.
 * Die Relying-Party-ID kommt je Mandant aus settings.passkeys.rp_id (sonst die Domain),
 * gesetzt in der Middleware TenantWebAuthn. Ein Passkey gilt nur auf dieser Domain.
 */
class PasskeyController extends Controller
{
    /** Anlegen, Schritt 1: Optionen fuer den Browser (angemeldet). */
    public function registerOptions(AttestationRequest $request): Responsable
    {
        return $request->fastRegistration()->toCreate();
    }

    /** Anlegen, Schritt 2: Antwort des Geraets speichern. */
    public function register(AttestedRequest $request): JsonResponse
    {
        $alias = trim((string) $request->input('alias', ''));
        $id = $request->save(['alias' => $alias !== '' ? mb_substr($alias, 0, 60) : null]);

        return response()->json(['ok' => true, 'id' => $id, 'anzahl' => $request->user()->webAuthnCredentials()->count()]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->webAuthnCredentials()->whereKey($id)->delete();

        return back()->with('meldung', 'Passkey entfernt.');
    }

    /** Anmelden, Schritt 1: Herausforderung (mit Mailadresse, sonst fuer alle gespeicherten Passkeys des Geraets). */
    public function loginOptions(AssertionRequest $request): Responsable
    {
        $data = $request->validate(['email' => ['nullable', 'email']]);

        return $request->fastLogin()->toVerify(filled($data['email'] ?? null) ? ['email' => strtolower(trim($data['email']))] : null);
    }

    /** Anmelden, Schritt 2: Signatur pruefen und anmelden. */
    public function login(AssertedRequest $request): JsonResponse
    {
        $key = 'passkey:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['ok' => false, 'fehler' => 'Zu viele Versuche. Bitte in ein paar Minuten nochmals.'], 429);
        }
        RateLimiter::hit($key, 600);

        $user = $request->login(remember: true);
        if (! $user) {
            return response()->json(['ok' => false, 'fehler' => 'Der Passkey passt nicht. Versuch es nochmals oder lass dir einen Link schicken.'], 422);
        }
        if (! $user->hasAccessTo()) {
            Auth::logout();

            return response()->json(['ok' => false, 'fehler' => 'Für diesen Bereich hast du keinen Zugang.'], 403);
        }
        RateLimiter::clear($key);
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json(['ok' => true, 'weiter' => MagicLink::cleanWeiter($request->input('weiter')) ?: route('home')]);
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OauthClient;
use App\Models\OauthCode;
use App\Models\OauthRefreshToken;
use App\Tenancy\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * OAuth 2.1 Autorisierungsserver fuer den MCP-Server (/api/mcp): Beschreibung (RFC 8414, RFC 9728),
 * Registrierung des Assistenten (RFC 7591), Freigabe durch die angemeldete Person, Token mit PKCE,
 * Erneuerung. Tokens sind Sanctum-Tokens mit der Faehigkeit mcp, darum gelten dieselben Regeln wie fuer
 * Schluessel aus dem Profil (nur Inhaberin und Team).
 */
class OAuthController extends Controller
{
    public const ZUGANG_TAGE = 7;

    public const ERNEUERUNG_TAGE = 90;

    public function __construct(protected Branding $branding) {}

    /** RFC 8414: wo liegen Freigabe, Token und Registrierung. */
    public function server(Request $request): JsonResponse
    {
        $basis = $request->getSchemeAndHttpHost();

        return response()->json([
            'issuer' => $basis,
            'authorization_endpoint' => $basis.'/oauth/authorize',
            'token_endpoint' => $basis.'/oauth/token',
            'registration_endpoint' => $basis.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp'],
            'service_documentation' => $basis.'/hilfe',
        ]);
    }

    /** RFC 9728: welcher Autorisierungsserver gehoert zum MCP-Server. */
    public function resource(Request $request): JsonResponse
    {
        $basis = $request->getSchemeAndHttpHost();

        return response()->json([
            'resource' => $basis.'/api/mcp',
            'authorization_servers' => [$basis],
            'scopes_supported' => ['mcp'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => $this->branding->appName(),
        ]);
    }

    /** RFC 7591: der Assistent registriert sich selbst, oeffentlicher Client ohne Geheimnis (PKCE). */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'string', 'max:500'],
        ]);
        foreach ($data['redirect_uris'] as $uri) {
            $ok = str_starts_with($uri, 'https://') || preg_match('~^http://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $uri);
            if (! $ok) {
                return response()->json(['error' => 'invalid_redirect_uri', 'error_description' => 'Nur https oder localhost.'], 400);
            }
        }

        $client = OauthClient::create([
            'client_id' => Str::random(32),
            'name' => Str::limit(trim((string) ($data['client_name'] ?? '')) ?: 'Assistent', 120, ''),
            'redirect_uris' => array_values($data['redirect_uris']),
        ]);

        return response()->json([
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'scope' => 'mcp',
        ], 201);
    }

    /** Freigabeseite: die angemeldete Person sieht, wer Zugriff will. */
    public function authorize(Request $request): View|RedirectResponse|\Illuminate\Http\Response
    {
        $client = $this->clientAusAnfrage($request);
        if (! $client instanceof OauthClient) {
            return $client;
        }
        if (($request->query('response_type') ?: 'code') !== 'code' || ($request->query('code_challenge_method') ?: 'S256') !== 'S256' || ! preg_match('#^[A-Za-z0-9._~-]{43,128}$#', (string) $request->query('code_challenge'))) {
            return $this->zurueckMitFehler($request->query('redirect_uri'), 'invalid_request', 'response_type=code und code_challenge (S256) sind noetig.', $request->query('state'));
        }

        return view('auth.oauth', [
            'client' => $client,
            'darf' => $request->user()->canManageCurrentTenant(),
            'felder' => ['client_id', 'redirect_uri', 'code_challenge', 'state', 'scope', 'resource'],
            'werte' => $request->only(['client_id', 'redirect_uri', 'code_challenge', 'state', 'scope', 'resource']),
            'coach' => $this->branding->coachName(),
        ]);
    }

    /** Erlauben oder ablehnen. Erlauben gibt einen Einmal-Code (10 Minuten) an die Rueckadresse. */
    public function approve(Request $request): RedirectResponse|\Illuminate\Http\Response
    {
        $client = $this->clientAusAnfrage($request);
        if (! $client instanceof OauthClient) {
            return $client;
        }
        $state = $request->input('state');
        if ($request->input('entscheidung') !== 'erlauben') {
            return $this->zurueckMitFehler($request->input('redirect_uri'), 'access_denied', 'Zugriff abgelehnt.', $state);
        }
        if (! $request->user()->canManageCurrentTenant()) {
            return $this->zurueckMitFehler($request->input('redirect_uri'), 'access_denied', 'Nur die Inhaberin und das Team duerfen Assistenten verbinden.', $state);
        }

        $code = Str::random(48);
        OauthCode::create([
            'oauth_client_id' => $client->id,
            'user_id' => $request->user()->id,
            'code_hash' => hash('sha256', $code),
            'redirect_uri' => $request->input('redirect_uri'),
            'code_challenge' => $request->input('code_challenge'),
            'scope' => 'mcp',
            'expires_at' => now()->addMinutes(10),
        ]);
        $client->forceFill(['last_used_at' => now()])->save();

        return redirect()->away($this->mitQuery($request->input('redirect_uri'), array_filter(['code' => $code, 'state' => $state], fn ($v) => $v !== null && $v !== '')));
    }

    /** Token-Endpunkt: Code gegen Token (PKCE) oder Erneuerung. */
    public function token(Request $request): JsonResponse
    {
        $grant = (string) $request->input('grant_type');
        $client = OauthClient::where('client_id', (string) $request->input('client_id'))->first();
        if (! $client) {
            return $this->fehler('invalid_client', 'Unbekannter Client.', 401);
        }

        if ($grant === 'authorization_code') {
            $code = OauthCode::where('code_hash', hash('sha256', (string) $request->input('code')))->where('oauth_client_id', $client->id)->first();
            if (! $code || $code->used_at || $code->expires_at->isPast()) {
                return $this->fehler('invalid_grant', 'Code unbekannt, verbraucht oder abgelaufen.');
            }
            if ((string) $request->input('redirect_uri') !== $code->redirect_uri) {
                return $this->fehler('invalid_grant', 'redirect_uri passt nicht.');
            }
            $verifier = (string) $request->input('code_verifier');
            if ($verifier === '' || rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=') !== $code->code_challenge) {
                return $this->fehler('invalid_grant', 'code_verifier passt nicht.');
            }
            $code->forceFill(['used_at' => now()])->save();

            return $this->tokenAntwort($client, $code->user, null);
        }

        if ($grant === 'refresh_token') {
            $alt = OauthRefreshToken::where('token_hash', hash('sha256', (string) $request->input('refresh_token')))->where('oauth_client_id', $client->id)->first();
            if (! $alt || $alt->revoked_at || $alt->expires_at->isPast()) {
                return $this->fehler('invalid_grant', 'Erneuerungs-Token unbekannt, widerrufen oder abgelaufen.');
            }
            $alt->forceFill(['revoked_at' => now()])->save();
            if ($alt->access_token_id) {
                PersonalAccessToken::where('id', $alt->access_token_id)->delete();
            }

            return $this->tokenAntwort($client, $alt->user, $alt);
        }

        return $this->fehler('unsupported_grant_type', 'Nur authorization_code und refresh_token.');
    }

    protected function tokenAntwort(OauthClient $client, $user, ?OauthRefreshToken $vorher): JsonResponse
    {
        $zugang = $user->createToken(Str::limit($client->name, 40, '').' (OAuth)', ['lesen', 'mcp'], now()->addDays(self::ZUGANG_TAGE));
        $erneuerung = Str::random(64);
        OauthRefreshToken::create([
            'oauth_client_id' => $client->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $erneuerung),
            'access_token_id' => $zugang->accessToken->id,
            'expires_at' => $vorher?->expires_at ?? now()->addDays(self::ERNEUERUNG_TAGE),
        ]);
        $client->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'access_token' => $zugang->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ZUGANG_TAGE * 86400,
            'refresh_token' => $erneuerung,
            'scope' => 'mcp',
        ])->header('Cache-Control', 'no-store');
    }

    protected function clientAusAnfrage(Request $request): OauthClient|\Illuminate\Http\Response
    {
        $client = OauthClient::where('client_id', (string) $request->input('client_id'))->first();
        $uri = (string) $request->input('redirect_uri');
        if (! $client) {
            return response('Unbekannter Client. Der Assistent muss sich zuerst registrieren.', 400);
        }
        if ($uri === '' || ! $client->erlaubtRedirect($uri)) {
            return response('Die Rueckadresse (redirect_uri) passt nicht zur Registrierung.', 400);
        }

        return $client;
    }

    protected function zurueckMitFehler(?string $uri, string $fehler, string $text, ?string $state): RedirectResponse
    {
        return redirect()->away($this->mitQuery((string) $uri, array_filter(['error' => $fehler, 'error_description' => $text, 'state' => $state], fn ($v) => $v !== null && $v !== '')));
    }

    protected function mitQuery(string $uri, array $params): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($params);
    }

    protected function fehler(string $code, string $text, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $code, 'error_description' => $text], $status)->header('Cache-Control', 'no-store');
    }
}

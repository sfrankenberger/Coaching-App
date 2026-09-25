<?php

namespace App\Booking;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;
use RuntimeException;

/**
 * Google-Kalender ueber ein Dienstkonto (JWT, RS256). Schluessel in tenants.settings.google.service_account
 * (JSON mit client_email und private_key), Kalender in settings.booking.calendar_id.
 */
class GoogleCalendar
{
    public function __construct(protected CurrentTenant $current) {}

    protected function konto(): array
    {
        $k = $this->current->get()?->setting('google.service_account');
        $k = is_string($k) ? json_decode($k, true) : $k;

        return is_array($k) ? $k : [];
    }

    public function konfiguriert(): bool
    {
        $k = $this->konto();

        return filled($k['client_email'] ?? null) && filled($k['private_key'] ?? null) && filled($this->kalender());
    }

    /** Buchung in der App eingeschaltet (settings.booking.enabled) und Kalender angebunden. */
    public function aktiv(): bool
    {
        $tenant = $this->current->get();

        return $tenant && Feature::for($tenant)->active('buchung') && $this->konfiguriert();
    }

    public function kalender(): ?string
    {
        return $this->current->get()?->setting('booking.calendar_id') ?: null;
    }

    protected function token(): string
    {
        $tenant = $this->current->getOrFail();

        return Cache::remember('google-token-'.$tenant->id, 3000, function () {
            $k = $this->konto();
            if (! $this->konfiguriert()) {
                throw new RuntimeException('Kein Google-Dienstkonto oder Kalender hinterlegt.');
            }
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $jetzt = time();
            $kopf = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $inhalt = $b64(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/calendar',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $jetzt, 'exp' => $jetzt + 3600]));
            $signatur = '';
            if (! openssl_sign($kopf.'.'.$inhalt, $signatur, $k['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Google: Schluessel des Dienstkontos ist ungueltig.');
            }
            $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $kopf.'.'.$inhalt.'.'.$b64($signatur),
            ]);
            if (! $r->successful() || blank($r->json('access_token'))) {
                throw new RuntimeException('Google-Anmeldung: '.mb_substr((string) $r->body(), 0, 200));
            }

            return (string) $r->json('access_token');
        });
    }

    protected function url(string $weg = ''): string
    {
        return 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode((string) $this->kalender()).'/events'.$weg;
    }

    /** Eintraege im Zeitraum: [{summary, start, ende, ganztags, frei}] */
    public function eintraege(Carbon $von, Carbon $bis): array
    {
        $out = [];
        $seite = null;
        $runde = 0;
        do {
            $r = Http::withToken($this->token())->timeout(25)->get($this->url(), array_filter([
                'timeMin' => $von->copy()->utc()->toRfc3339String(), 'timeMax' => $bis->copy()->utc()->toRfc3339String(),
                'singleEvents' => 'true', 'orderBy' => 'startTime', 'maxResults' => 250, 'pageToken' => $seite,
            ]));
            if (! $r->successful()) {
                throw new RuntimeException('Google-Kalender meldet '.$r->status().': '.mb_substr((string) $r->body(), 0, 200));
            }
            foreach ($r->json('items') ?? [] as $e) {
                if (($e['status'] ?? '') === 'cancelled') {
                    continue;
                }
                $ganztags = isset($e['start']['date']);
                $tz = $this->current->get()?->timezone ?: config('app.timezone');
                $out[] = [
                    'id' => $e['id'] ?? null,
                    'summary' => (string) ($e['summary'] ?? ''),
                    'start' => $ganztags ? Carbon::parse($e['start']['date'], $tz) : Carbon::parse($e['start']['dateTime']),
                    'ende' => $ganztags ? Carbon::parse($e['end']['date'], $tz) : Carbon::parse($e['end']['dateTime']),
                    'ganztags' => $ganztags,
                    'frei' => ($e['transparency'] ?? 'opaque') === 'transparent',
                ];
            }
            $seite = $r->json('nextPageToken');
        } while ($seite && ++$runde < 10);

        return $out;
    }

    public function anlegen(string $titel, Carbon $start, Carbon $ende, string $text = ''): ?string
    {
        $r = Http::withToken($this->token())->timeout(20)->post($this->url(), [
            'summary' => $titel, 'description' => $text,
            'start' => ['dateTime' => $start->copy()->utc()->toRfc3339String()],
            'end' => ['dateTime' => $ende->copy()->utc()->toRfc3339String()],
        ]);

        return $r->successful() ? $r->json('id') : throw new RuntimeException('Google-Eintrag: '.$r->status());
    }

    public function loeschen(string $id): void
    {
        Http::withToken($this->token())->timeout(20)->delete($this->url('/'.rawurlencode($id)));
    }
}

<?php

namespace App\Shop;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * bexio (Schweiz). Zugang je Mandant in tenants.settings.buchhaltung.bexio: entweder ein fester
 * Zugriffstoken (token) oder eine dauerhafte Verbindung per OAuth (client_id, client_secret,
 * refresh_token, access_token, access_bis). Das Refresh-Token rotiert bei jedem Erneuern, darum
 * erneuert immer nur ein Lauf gleichzeitig. Die Verbindung gehoert der App allein, nie mit einem
 * anderen System (etwa der Website) teilen.
 */
class Bexio extends Buchhaltung
{
    public const API = 'https://api.bexio.com';

    public const AUTH = 'https://auth.bexio.com/realms/bexio/protocol/openid-connect';

    public const SCOPES = 'openid profile email offline_access company_profile contact_show kb_invoice_show';

    /** bexio kb_item_status_id -> Status der App */
    protected const STATUS_IDS = [7 => 'entwurf', 8 => 'offen', 9 => 'bezahlt', 16 => 'teilweise', 19 => 'storniert', 31 => 'gemahnt'];

    protected bool $erneuert = false;

    public function name(): string
    {
        return 'bexio';
    }

    public function einstellungen(): array
    {
        return (array) $this->tenant->setting('buchhaltung.bexio', []);
    }

    public function verbunden(): bool
    {
        $e = $this->einstellungen();

        return filled($e['token'] ?? null) || (filled($e['refresh_token'] ?? null) && filled($e['client_id'] ?? null) && filled($e['client_secret'] ?? null));
    }

    /** Firma laut bexio, seit wann verbunden. */
    public function stand(): array
    {
        $e = $this->einstellungen();

        return ['firma' => $e['firma'] ?? null, 'seit' => $e['verbunden_am'] ?? null, 'art' => filled($e['token'] ?? null) ? 'token' : (filled($e['refresh_token'] ?? null) ? 'oauth' : null), 'fehler' => $e['fehler'] ?? null];
    }

    /* ---------- OAuth ---------- */

    public function authUrl(string $state, string $redirect): string
    {
        return self::AUTH.'/auth?'.http_build_query([
            'client_id' => $this->einstellungen()['client_id'] ?? '',
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    /** Tauscht den Code nach der Zustimmung gegen Tokens und merkt sich die Firma. */
    public function verbinden(string $code, string $redirect): void
    {
        $this->tokenHolen(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect]);
        $firma = null;
        try {
            $firma = $this->req('GET', '/2.0/company_profile')[0]['name'] ?? null;
        } catch (RuntimeException $e) {
            Log::warning('bexio: Firma nicht gelesen: '.$e->getMessage());
        }
        $this->speichern(['firma' => $firma, 'verbunden_am' => now()->toIso8601String(), 'fehler' => null]);
    }

    public function trennen(): void
    {
        $this->speichern(['refresh_token' => null, 'access_token' => null, 'access_bis' => null, 'token' => null, 'firma' => null, 'verbunden_am' => null, 'fehler' => null]);
    }

    protected function tokenHolen(array $felder): string
    {
        $e = $this->einstellungen();
        $r = Http::asForm()->acceptJson()->timeout(20)->post(self::AUTH.'/token', $felder + ['client_id' => $e['client_id'] ?? '', 'client_secret' => $e['client_secret'] ?? '']);
        $j = $r->json();
        if (! $r->successful() || empty($j['access_token'])) {
            $grund = $j['error_description'] ?? $j['error'] ?? ('HTTP '.$r->status());
            $this->speichern(['fehler' => now()->toIso8601String().' '.$grund]);
            throw new RuntimeException('bexio-Anmeldung abgelehnt: '.$grund);
        }
        $neu = ['access_token' => $j['access_token'], 'access_bis' => now()->addSeconds((int) ($j['expires_in'] ?? 3600) - 60)->toIso8601String(), 'fehler' => null];
        if (! empty($j['refresh_token'])) {
            $neu['refresh_token'] = $j['refresh_token'];
        }
        $this->speichern($neu);

        return $j['access_token'];
    }

    /** Gueltiger Zugriffstoken: fester Token, sonst der gespeicherte, sonst erneuern (nur ein Lauf gleichzeitig). */
    protected function accessToken(bool $erzwingen = false): string
    {
        $e = $this->einstellungen();
        if (filled($e['token'] ?? null)) {
            return trim($e['token']);
        }
        if (! $erzwingen && filled($e['access_token'] ?? null) && filled($e['access_bis'] ?? null) && now()->lt($e['access_bis'])) {
            return $e['access_token'];
        }
        if (blank($e['refresh_token'] ?? null)) {
            throw new RuntimeException('Kein bexio-Zugang hinterlegt.');
        }

        return Cache::lock('bexio:refresh:'.$this->tenant->id, 30)->block(20, function () use ($erzwingen) {
            $e = $this->einstellungen();
            if (! $erzwingen && filled($e['access_token'] ?? null) && filled($e['access_bis'] ?? null) && now()->lt($e['access_bis'])) {
                return $e['access_token'];   // ein anderer Lauf hat gerade erneuert
            }

            return $this->tokenHolen(['grant_type' => 'refresh_token', 'refresh_token' => $e['refresh_token']]);
        });
    }

    /** Aufruf der API. Bei 401 einmal erneuern und wiederholen. Fehler werfen. */
    public function req(string $method, string $pfad, ?array $body = null): array
    {
        $r = $this->senden($method, $pfad, $body, $this->accessToken());
        if ($r->status() === 401 && ! $this->erneuert && blank($this->einstellungen()['token'] ?? null)) {
            $this->erneuert = true;
            $r = $this->senden($method, $pfad, $body, $this->accessToken(true));
        }
        if (! $r->successful()) {
            $m = $r->json('message') ?? mb_substr($r->body(), 0, 300);
            throw new RuntimeException('bexio '.$r->status().' bei '.$method.' '.$pfad.': '.$m);
        }

        return (array) $r->json();
    }

    protected function senden(string $method, string $pfad, ?array $body, string $token): Response
    {
        $client = Http::withToken($token)->acceptJson()->timeout(25);

        return $body === null ? $client->send($method, self::API.$pfad) : $client->send($method, self::API.$pfad, ['json' => $body]);
    }

    /* ---------- Rechnungen ---------- */

    /** Kontakt-ID zur Person: an der Mitgliedschaft gemerkt, sonst per Mail-Adresse gesucht. */
    public function kontaktId(User $user): ?int
    {
        $m = $user->membershipIn($this->tenant);
        if ($id = (int) ($m?->setting('buchhaltung.kontakt_id') ?? 0)) {
            return $id;
        }
        $r = $this->req('POST', '/2.0/contact/search?limit=1', [['field' => 'mail', 'value' => mb_strtolower($user->email), 'criteria' => '=']]);
        $id = (int) ($r[0]['id'] ?? 0);
        if ($id && $m) {
            $m->forceFill(['settings' => array_replace_recursive(is_array($m->settings) ? $m->settings : [], ['buchhaltung' => ['kontakt_id' => $id]])])->save();
        }

        return $id ?: null;
    }

    public function rechnungen(User $user, bool $frisch = false): Collection
    {
        $key = 'bexio:'.$this->tenant->id.':rechnungen:'.$user->id;
        if ($frisch) {
            Cache::forget($key);
        }

        return collect(Cache::remember($key, now()->addMinutes(10), function () use ($user) {
            if (! $this->verbunden() || ! ($kid = $this->kontaktId($user))) {
                return [];
            }
            $liste = $this->req('POST', '/2.0/kb_invoice/search?limit=500', [['field' => 'contact_id', 'value' => $kid, 'criteria' => '=']]);
            $out = [];
            foreach ($liste as $i) {
                $status = self::STATUS_IDS[(int) ($i['kb_item_status_id'] ?? 8)] ?? 'offen';
                if ($status === 'entwurf') {
                    continue;
                }
                $out[] = [
                    'id' => (int) $i['id'],
                    'nr' => (string) ($i['document_nr'] ?? ''),
                    'titel' => trim(strip_tags((string) ($i['title'] ?? ''))),
                    'datum' => (string) ($i['is_valid_from'] ?? ''),
                    'faellig' => (string) ($i['is_valid_to'] ?? ''),
                    'betrag' => (float) ($i['total'] ?? 0),
                    'waehrung' => (int) ($i['currency_id'] ?? 1) === 1 ? 'CHF' : 'EUR',
                    'status' => $status,
                    'link' => (string) ($i['network_link'] ?? ''),
                ];
            }
            usort($out, fn ($a, $b) => strcmp($b['datum'], $a['datum']));

            return $out;
        }));
    }

    public function pdf(int $id): ?string
    {
        $r = $this->req('GET', '/2.0/kb_invoice/'.$id.'/pdf');
        $bin = base64_decode((string) ($r['content'] ?? ''), true);

        return $bin && str_starts_with($bin, '%PDF') ? $bin : null;
    }

    /** Schreibt in tenants.settings.buchhaltung.bexio, immer auf dem frischen Stand aus der Datenbank. */
    protected function speichern(array $werte): void
    {
        $frisch = Tenant::query()->find($this->tenant->id) ?? $this->tenant;
        $s = $frisch->settings ?? [];
        $s['buchhaltung']['bexio'] = array_merge($s['buchhaltung']['bexio'] ?? [], $werte);
        $frisch->forceFill(['settings' => $s])->save();
        $this->tenant->settings = $s;
    }
}

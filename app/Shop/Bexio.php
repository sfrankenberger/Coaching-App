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

    public const SCOPES = 'openid profile email offline_access company_profile contact_show contact_edit kb_invoice_show kb_invoice_edit bank_account_show accounting';

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
        try {
            $this->stammdaten();
        } catch (RuntimeException $e) {
            Log::warning('bexio: Stammdaten nicht geladen: '.$e->getMessage());
        }
    }

    /* ---------- Stammdaten und Vorgaben zum Schreiben ---------- */

    /**
     * Liest aus bexio, was zum Rechnungschreiben gebraucht wird (Benutzer, Bankkonten, Ertragskonten,
     * Steuersaetze, Waehrungen, Sprachen) und setzt Vorgaben, wo noch nichts gewaehlt ist.
     */
    public function stammdaten(): array
    {
        $s = [];
        $me = $this->req('GET', '/3.0/users/me');
        $s['me'] = ['id' => (int) ($me['id'] ?? 0), 'name' => trim(($me['firstname'] ?? '').' '.($me['lastname'] ?? '')), 'email' => $me['email'] ?? ''];
        $s['bank'] = [];
        foreach ($this->still(fn () => $this->req('GET', '/3.0/banking/accounts')) as $b) {
            $s['bank'][(int) $b['id']] = trim(($b['name'] ?? 'Konto').' '.($b['iban_nr'] ?? ($b['iban'] ?? '')));
        }
        $s['konten'] = [];
        foreach ($this->still(fn () => $this->req('GET', '/2.0/accounts?limit=2000')) as $k) {
            $nr = (string) ($k['account_no'] ?? '');
            if ($nr !== '' && $nr[0] === '3' && ! empty($k['is_active'])) {
                $s['konten'][(int) $k['id']] = $nr.' '.($k['name'] ?? '');
            }
        }
        $s['steuern'] = [];
        foreach ($this->still(fn () => $this->req('GET', '/3.0/taxes?scope=active&types=sales_tax')) as $t) {
            $s['steuern'][(int) $t['id']] = trim(($t['display_name'] ?? ($t['code'] ?? 'MWST')).(isset($t['value']) ? ' ('.$t['value'].'%)' : ''));
        }
        $s['waehrungen'] = [];
        foreach ($this->still(fn () => $this->req('GET', '/3.0/currencies')) as $w) {
            $s['waehrungen'][(int) $w['id']] = strtoupper((string) ($w['name'] ?? ''));
        }
        $s['sprachen'] = [];
        foreach ($this->still(fn () => $this->req('GET', '/2.0/language')) as $l) {
            $s['sprachen'][(int) $l['id']] = (string) ($l['name'] ?? ($l['iso_639_1'] ?? ''));
        }

        $v = $this->schreiben();
        $v['user_id'] = $v['user_id'] ?? $s['me']['id'];
        if (empty($v['bank_account_id']) && $s['bank']) {
            $v['bank_account_id'] = array_key_first($s['bank']);
        }
        if (empty($v['zahlung_bank_account_id']) && $s['bank']) {
            $v['zahlung_bank_account_id'] = array_key_first($s['bank']);
        }
        if (empty($v['account_id']) && $s['konten']) {
            foreach (['3400', '3200', '3000'] as $wunsch) {
                foreach ($s['konten'] as $id => $label) {
                    if (str_starts_with($label, $wunsch.' ')) {
                        $v['account_id'] = $id;
                        break 2;
                    }
                }
            }
            $v['account_id'] ??= array_key_first($s['konten']);
        }
        if (empty($v['language_id'])) {
            foreach ($s['sprachen'] as $id => $n) {
                if (stripos($n, 'deutsch') !== false || stripos($n, 'german') !== false || $n === 'de') {
                    $v['language_id'] = $id;
                }
            }
        }
        $v['frist'] ??= 30;
        $this->speichern(['stammdaten' => $s, 'schreiben' => $v]);

        return $s;
    }

    /** Vorgaben zum Schreiben (Benutzer, Konten, Frist, Kopie, Zugang bei Rechnung). */
    public function schreiben(): array
    {
        return (array) ($this->einstellungen()['schreiben'] ?? []);
    }

    public function kannSchreiben(): bool
    {
        $v = $this->schreiben();

        return $this->verbunden() && ! empty($v['user_id']) && ! empty($v['account_id']);
    }

    /** Ein Aufruf, der fehlen darf: leere Liste statt Abbruch. */
    protected function still(callable $fn): array
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            Log::warning('bexio: '.$e->getMessage());

            return [];
        }
    }

    /** Kontakt zur Person: vorhanden nehmen, sonst anlegen (Privatperson, Nachname, Vorname, Mail). */
    public function kontaktAnlegen(User $user): int
    {
        if ($id = $this->kontaktId($user)) {
            return $id;
        }
        $teile = preg_split('/\s+/', trim($user->name), 2);
        $vorname = $teile[0] ?? '';
        $nachname = $teile[1] ?? '';
        $uid = (int) ($this->schreiben()['user_id'] ?? 0);
        $c = $this->req('POST', '/2.0/contact', [
            'contact_type_id' => 2,
            'name_1' => $nachname !== '' ? $nachname : ($vorname !== '' ? $vorname : $user->email),
            'name_2' => $nachname !== '' ? $vorname : null,
            'mail' => mb_strtolower($user->email),
            'user_id' => $uid,
            'owner_id' => $uid,
        ]);
        $id = (int) ($c['id'] ?? 0);
        if (! $id) {
            throw new RuntimeException('bexio hat keine Kontakt-ID geliefert.');
        }
        if ($m = $user->membershipIn($this->tenant)) {
            $m->forceFill(['settings' => array_replace_recursive(is_array($m->settings) ? $m->settings : [], ['buchhaltung' => ['kontakt_id' => $id]])])->save();
        }

        return $id;
    }

    public function waehrungId(string $code): int
    {
        foreach ((array) ($this->einstellungen()['stammdaten']['waehrungen'] ?? []) as $id => $n) {
            if (strtoupper((string) $n) === strtoupper($code)) {
                return (int) $id;
            }
        }
        throw new RuntimeException('Die Währung '.$code.' ist in bexio nicht angelegt.');
    }

    public function rechnungAnlegen(User $user, string $titel, array $positionen, string $waehrung, bool $bezahlt, string $referenz): array
    {
        $v = $this->schreiben();
        $kid = $this->kontaktAnlegen($user);
        $tax = (int) ($v['tax_id'] ?? 0);
        $pos = [];
        foreach ($positionen as $p) {
            $anzahl = max(1, (int) ($p['anzahl'] ?? 1));
            $zeile = ['type' => 'KbPositionCustom', 'amount' => (string) $anzahl, 'unit_price' => number_format((float) $p['betrag'] / $anzahl, 2, '.', ''), 'account_id' => (int) $v['account_id'], 'text' => (string) $p['text']];
            if ($tax) {
                $zeile['tax_id'] = $tax;
            }
            $pos[] = $zeile;
        }
        if (! $pos) {
            throw new RuntimeException('Rechnung ohne Positionen.');
        }
        $frist = $bezahlt ? 0 : max(0, (int) ($v['frist'] ?? 30));
        $faellig = now($this->tenant->timezone ?: config('app.timezone'))->addDays($frist)->toDateString();
        $vorname = $user->vorname();
        $coach = trim((string) ($v['absender'] ?? '')) ?: (string) $this->tenant->setting('coach_name', $this->tenant->name);
        $body = [
            'title' => $titel,
            'contact_id' => $kid,
            'user_id' => (int) $v['user_id'],
            'mwst_type' => (int) ($v['mwst_type'] ?? ($tax ? 0 : 2)),
            'mwst_is_net' => false,
            'is_valid_from' => now($this->tenant->timezone ?: config('app.timezone'))->toDateString(),
            'is_valid_to' => $faellig,
            'api_reference' => $referenz,
            'header' => ($vorname ? 'Liebe '.e($vorname) : 'Hallo').'<br><br>'.($bezahlt ? 'Herzlichen Dank für deine Bestellung. Die Zahlung ist bereits eingegangen, hier deine Quittung:' : 'Wir erlauben uns, dir wie folgt in Rechnung zu stellen:'),
            'footer' => 'Bei Fragen stehen wir dir gerne zur Verfügung.<br><br>Freundliche Grüsse<br>'.e($coach),
            'positions' => $pos,
            'payment_type_id' => (int) ($v['payment_type_id'] ?? 4),
            'currency_id' => $this->waehrungId($waehrung),
        ];
        if (! empty($v['template_slug'])) {
            $body['template_slug'] = (string) $v['template_slug'];
        }
        if (! empty($v['language_id'])) {
            $body['language_id'] = (int) $v['language_id'];
        }
        if ($bank = (int) ($v['bank_account_id_'.strtoupper($waehrung)] ?? $v['bank_account_id'] ?? 0)) {
            $body['bank_account_id'] = $bank;
        }
        $r = $this->req('POST', '/2.0/kb_invoice', $body);
        $id = (int) ($r['id'] ?? 0);
        if (! $id) {
            throw new RuntimeException('bexio hat keine Rechnungs-ID geliefert.');
        }
        $this->still(fn () => $this->req('POST', '/2.0/kb_invoice/'.$id.'/issue'));
        if ($bezahlt) {
            $this->still(function () use ($id, $positionen, $waehrung) {
                $this->zahlungBuchen($id, array_sum(array_map(fn ($p) => (float) $p['betrag'], $positionen)), $waehrung);

                return [];
            });
        }
        // bexio erzeugt den Link zum Online-Bezahlen erst beim Versand: Kopie an die eigene Adresse
        if (! $bezahlt && filled($v['kopie_mail'] ?? null)) {
            $this->still(fn () => $this->req('POST', '/2.0/kb_invoice/'.$id.'/send', [
                'recipient_email' => $v['kopie_mail'], 'subject' => 'Kopie: Rechnung an '.$user->name,
                'message' => 'Kopie zur Ablage. Die Person bekommt ihre Mail aus der App.<br><br>Rechnung online: [Network Link]',
                'mark_as_open' => true, 'attach_pdf' => false,
            ]));
        }
        $info = $this->still(fn () => $this->req('GET', '/2.0/kb_invoice/'.$id));
        Cache::forget('bexio:'.$this->tenant->id.':rechnungen:'.$user->id);

        return ['id' => $id, 'nr' => (string) ($info['document_nr'] ?? ''), 'link' => (string) ($info['network_link'] ?? ''), 'faellig' => $faellig];
    }

    public function zahlungBuchen(int $rechnungId, float $betrag, string $waehrung): void
    {
        $v = $this->schreiben();
        $body = ['date' => now($this->tenant->timezone ?: config('app.timezone'))->toDateString(), 'value' => number_format($betrag, 2, '.', '')];
        if ($konto = (int) ($v['zahlung_bank_account_id_'.strtoupper($waehrung)] ?? $v['zahlung_bank_account_id'] ?? 0)) {
            $body['bank_account_id'] = $konto;
        }
        $this->req('POST', '/2.0/kb_invoice/'.$rechnungId.'/payment', $body);
    }

    public function rechnungStatus(int $rechnungId): ?string
    {
        $r = $this->req('GET', '/2.0/kb_invoice/'.$rechnungId);

        return isset($r['kb_item_status_id']) ? (self::STATUS_IDS[(int) $r['kb_item_status_id']] ?? 'offen') : null;
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

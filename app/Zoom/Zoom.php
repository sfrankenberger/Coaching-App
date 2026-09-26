<?php

namespace App\Zoom;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zoom-API je Mandant (Server-to-Server-OAuth). Zugang in tenants.settings.zoom:
 * account_id, client_id, client_secret (Geheimnisse, nur auf dem Server).
 */
class Zoom
{
    public function __construct(protected CurrentTenant $current) {}

    public function konfiguriert(): bool
    {
        $z = (array) ($this->current->get()?->setting('zoom') ?? []);

        return filled($z['account_id'] ?? null) && filled($z['client_id'] ?? null) && filled($z['client_secret'] ?? null);
    }

    protected function token(): string
    {
        $tenant = $this->current->getOrFail();

        return Cache::remember('zoom-token-'.$tenant->id, 3000, function () use ($tenant) {
            $z = (array) $tenant->setting('zoom', []);
            if (! $this->konfiguriert()) {
                throw new RuntimeException('Keine Zoom-Zugangsdaten hinterlegt (settings.zoom).');
            }
            $r = Http::timeout(20)->withBasicAuth($z['client_id'], $z['client_secret'])->asForm()
                ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.rawurlencode($z['account_id']));
            if (! $r->successful() || blank($r->json('access_token'))) {
                throw new RuntimeException('Zoom-Anmeldung: '.mb_substr((string) $r->body(), 0, 200));
            }

            return (string) $r->json('access_token');
        });
    }

    protected function get(string $weg, array $abfrage = []): array
    {
        $r = Http::timeout(25)->withToken($this->token())->get('https://api.zoom.us/v2'.$weg, $abfrage);
        if ($r->status() === 401) {
            Cache::forget('zoom-token-'.$this->current->id());
        }
        if (! $r->successful()) {
            throw new RuntimeException('Zoom meldet '.$r->status().': '.mb_substr((string) $r->body(), 0, 200));
        }

        return (array) $r->json();
    }

    /** Meetingnummer aus einem Zoom-Link oder einer Nummer. */
    public static function nummer(?string $link): ?string
    {
        $link = trim((string) $link);
        if (preg_match('~/j/(\d{9,12})~', $link, $m)) {
            return $m[1];
        }

        return preg_match('~^\d{9,12}$~', $link) ? $link : null;
    }

    /** Vergangene Sitzungen eines Raums: [{uuid, start_time}] */
    public function sitzungen(string $nummer): array
    {
        return $this->get('/past_meetings/'.rawurlencode($nummer).'/instances')['meetings'] ?? [];
    }

    /** Teilnehmerliste einer Sitzung, Sekunden je Person zusammengezaehlt: [{name, mail, sekunden}] */
    public function teilnehmer(string $uuid): array
    {
        $kodiert = rawurlencode($uuid);
        if (str_contains($uuid, '/') || str_starts_with($uuid, '/')) {
            $kodiert = rawurlencode($kodiert);
        }
        $leute = [];
        $seite = '';
        $runde = 0;
        do {
            $r = $this->get('/past_meetings/'.$kodiert.'/participants', array_filter(['page_size' => 300, 'next_page_token' => $seite]));
            foreach ($r['participants'] ?? [] as $p) {
                $mail = strtolower(trim((string) ($p['user_email'] ?? '')));
                $name = trim((string) ($p['name'] ?? ''));
                $key = $mail ?: mb_strtolower($name);
                if ($key === '') {
                    continue;
                }
                $leute[$key] ??= ['name' => $name, 'mail' => $mail, 'sekunden' => 0];
                $leute[$key]['sekunden'] += (int) ($p['duration'] ?? 0);
                if ($leute[$key]['mail'] === '' && $mail !== '') {
                    $leute[$key]['mail'] = $mail;
                }
            }
            $seite = (string) ($r['next_page_token'] ?? '');
        } while ($seite !== '' && ++$runde < 10);

        return array_values($leute);
    }
}

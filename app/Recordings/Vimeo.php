<?php

namespace App\Recordings;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Vimeo-API je Mandant (Personal Access Token in tenants.settings.vimeo.token). */
class Vimeo
{
    public function __construct(protected CurrentTenant $current) {}

    public function konfiguriert(): bool
    {
        return filled($this->token());
    }

    protected function token(): ?string
    {
        return $this->current->get()?->setting('vimeo.token') ?: null;
    }

    protected function get(string $weg, array $abfrage = []): array
    {
        $token = $this->token();
        if (! $token) {
            throw new RuntimeException('Kein Vimeo-Zugang hinterlegt (settings.vimeo.token).');
        }
        $r = Http::timeout(25)->withToken($token)->accept('application/vnd.vimeo.*+json;version=3.4')
            ->get('https://api.vimeo.com'.$weg, $abfrage);
        if (! $r->successful()) {
            throw new RuntimeException('Vimeo meldet '.$r->status().': '.mb_substr((string) $r->body(), 0, 300));
        }

        return (array) $r->json();
    }

    /** Die letzten Videos des Kontos, neueste zuerst. */
    public function videos(int $anzahl = 20): array
    {
        return $this->get('/me/videos', [
            'per_page' => $anzahl, 'sort' => 'date', 'direction' => 'desc',
            'fields' => 'uri,name,link,duration,created_time,pictures.sizes,parent_folder.name',
        ])['data'] ?? [];
    }

    public static function id(array $video): string
    {
        return basename((string) ($video['uri'] ?? ''));
    }

    public static function bild(array $video): ?string
    {
        foreach ($video['pictures']['sizes'] ?? [] as $g) {
            if ((int) ($g['width'] ?? 0) >= 640) {
                return $g['link'] ?? null;
            }
        }

        return null;
    }

    public static function dauer(int $sekunden): string
    {
        return $sekunden >= 3600 ? intdiv($sekunden, 3600).' h '.str_pad((string) intdiv($sekunden % 3600, 60), 2, '0', STR_PAD_LEFT).' min' : max(1, intdiv($sekunden, 60)).' min';
    }

    /** Abschrift aus der Textspur (deutsch bevorzugt), null wenn es noch keine gibt. */
    public function abschrift(string $videoId): ?string
    {
        $spuren = $this->get('/videos/'.$videoId.'/texttracks')['data'] ?? [];
        $spur = collect($spuren)->filter(fn ($s) => filled($s['link'] ?? null))
            ->sortBy(fn ($s) => str_starts_with((string) ($s['language'] ?? ''), 'de') ? 0 : 1)->first();
        if (! $spur) {
            return null;
        }
        $vtt = Http::timeout(30)->get($spur['link']);

        return $vtt->successful() ? self::vttZuText((string) $vtt->body()) : null;
    }

    /** WEBVTT in Text mit einer Zeitmarke [MM:SS] etwa alle zwei Minuten. */
    public static function vttZuText(string $vtt): string
    {
        $text = '';
        $letzte = -999;
        $jetzt = 0;
        foreach (preg_split('~\r?\n~', $vtt) as $z) {
            $z = trim($z);
            if ($z === '' || $z === 'WEBVTT' || is_numeric($z)) {
                continue;
            }
            if (preg_match('~^(\d{2}):(\d{2}):(\d{2})~', $z, $m)) {
                $jetzt = (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];

                continue;
            }
            if ($jetzt - $letzte >= 120) {
                $text .= "\n[".sprintf('%02d:%02d', intdiv($jetzt, 60), $jetzt % 60).'] ';
                $letzte = $jetzt;
            }
            $text .= $z.' ';
        }

        return trim($text);
    }
}

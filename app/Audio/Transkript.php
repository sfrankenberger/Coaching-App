<?php

namespace App\Audio;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Sprachnachrichten abschreiben. Anthropic kann kein Audio, darum ein eigener Dienst je Mandant:
 * AssemblyAI (wie im alten Bereich) oder OpenAI Whisper. Schluessel in settings.audio, gepflegt unter /coach/verbindungen.
 */
class Transkript
{
    public const ANBIETER = ['assemblyai' => 'AssemblyAI', 'openai' => 'OpenAI Whisper'];

    public function __construct(protected CurrentTenant $current) {}

    /** Welcher Dienst laeuft, oder null, wenn keiner eingerichtet ist. */
    public function anbieter(?Tenant $tenant = null): ?string
    {
        $tenant ??= $this->current->get();
        $a = (string) $tenant?->setting('audio.anbieter');
        $key = $this->schluessel($tenant, $a);

        return array_key_exists($a, self::ANBIETER) && $key ? $a : null;
    }

    public function konfiguriert(?Tenant $tenant = null): bool
    {
        return $this->anbieter($tenant) !== null;
    }

    protected function schluessel(?Tenant $tenant, string $anbieter): ?string
    {
        $key = trim((string) $tenant?->setting('audio.'.$anbieter.'_key'));

        return $key !== '' ? $key : null;
    }

    /** Abschrift einer Audiodatei im Speicher (Pfad wie in messages.audio_path). Null, wenn nichts dabei herauskommt. */
    public function erstellen(string $pfad): ?string
    {
        $tenant = $this->current->getOrFail();
        $anbieter = $this->anbieter($tenant);
        if (! $anbieter || ! Storage::exists($pfad)) {
            return null;
        }
        $key = $this->schluessel($tenant, $anbieter);
        $sprache = (string) ($tenant->setting('audio.sprache') ?: 'de');
        $inhalt = Storage::get($pfad);
        if (strlen((string) $inhalt) < 100) {
            return null;
        }

        $text = $anbieter === 'openai'
            ? $this->openai($key, basename($pfad), $inhalt, $sprache)
            : $this->assemblyai($key, $inhalt, $sprache);

        return filled($text) ? trim($text) : null;
    }

    /**
     * Abschrift einer Audiodatei im Netz (Podcastfolge). Mit Zeitmarken kommen Absaetze wie "[12:34] Text",
     * die die KI fuer Kapitel braucht. AssemblyAI holt die Datei selbst, Whisper braucht sie hochgeladen.
     */
    public function ausUrl(string $url, bool $mitZeiten = true): ?string
    {
        $tenant = $this->current->getOrFail();
        $anbieter = $this->anbieter($tenant);
        if (! $anbieter || ! preg_match('~^https?://~', $url)) {
            return null;
        }
        $key = $this->schluessel($tenant, $anbieter);
        $sprache = (string) ($tenant->setting('audio.sprache') ?: 'de');
        if ($anbieter === 'openai') {
            $datei = Http::timeout(120)->get($url);
            if (! $datei->successful() || strlen($datei->body()) < 100) {
                throw new RuntimeException('Audio nicht ladbar ('.$datei->status().').');
            }
            $text = $this->openai($key, basename(parse_url($url, PHP_URL_PATH) ?: 'folge.mp3'), $datei->body(), $sprache, $mitZeiten);
        } else {
            $text = $this->assemblyai($key, null, $sprache, $url, $mitZeiten);
        }

        return filled($text) ? trim($text) : null;
    }

    /** AssemblyAI: Datei hochladen, Auftrag anlegen, warten bis fertig. */
    protected function assemblyai(string $key, ?string $inhalt, string $sprache, ?string $url = null, bool $mitZeiten = false): ?string
    {
        $basis = 'https://api.assemblyai.com/v2';
        if ($url === null) {
            $up = Http::timeout(120)->withHeaders(['authorization' => $key, 'content-type' => 'application/octet-stream'])->withBody((string) $inhalt, 'application/octet-stream')->post($basis.'/upload');
            if (! $up->successful() || blank($up->json('upload_url'))) {
                throw new RuntimeException('AssemblyAI: Hochladen fehlgeschlagen ('.$up->status().').');
            }
            $url = $up->json('upload_url');
        }
        $auftrag = Http::timeout(30)->withHeaders(['authorization' => $key])->post($basis.'/transcript', [
            'audio_url' => $url,
            'language_code' => $sprache,
            'punctuate' => true,
            'format_text' => true,
        ]);
        $id = $auftrag->json('id');
        if (! $auftrag->successful() || blank($id)) {
            throw new RuntimeException('AssemblyAI: Auftrag nicht angenommen ('.$auftrag->status().').');
        }
        for ($i = 0; $i < ($mitZeiten ? 400 : 100); $i++) {
            $stand = Http::timeout(30)->withHeaders(['authorization' => $key])->get($basis.'/transcript/'.$id);
            $status = (string) $stand->json('status');
            if ($status === 'completed') {
                if ($mitZeiten) {
                    $saetze = Http::timeout(30)->withHeaders(['authorization' => $key])->get($basis.'/transcript/'.$id.'/sentences')->json('sentences');
                    if (is_array($saetze) && $saetze !== []) {
                        return self::mitZeitmarken(array_map(fn ($s) => ['start' => (int) round(($s['start'] ?? 0) / 1000), 'text' => (string) ($s['text'] ?? '')], $saetze));
                    }
                }

                return (string) $stand->json('text');
            }
            if ($status === 'error') {
                throw new RuntimeException('AssemblyAI: '.($stand->json('error') ?: 'Fehler beim Abschreiben.'));
            }
            Sleep::for(3)->seconds();
        }

        throw new RuntimeException('AssemblyAI: Abschrift nicht rechtzeitig fertig.');
    }

    /** OpenAI Whisper: eine Anfrage mit der Datei. */
    protected function openai(string $key, string $name, string $inhalt, string $sprache, bool $mitZeiten = false): ?string
    {
        $r = Http::timeout(600)->withToken($key)
            ->attach('file', $inhalt, $name)
            ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => 'whisper-1', 'language' => $sprache, 'response_format' => $mitZeiten ? 'verbose_json' : 'json']);
        if (! $r->successful()) {
            throw new RuntimeException('OpenAI: Abschrift fehlgeschlagen ('.$r->status().').');
        }
        if ($mitZeiten && is_array($r->json('segments'))) {
            return self::mitZeitmarken(array_map(fn ($s) => ['start' => (int) round($s['start'] ?? 0), 'text' => (string) ($s['text'] ?? '')], $r->json('segments')));
        }

        return (string) $r->json('text');
    }

    /** Saetze zu Absaetzen von etwa 30 Sekunden mit Zeitmarke "[m:ss]" am Anfang. */
    public static function mitZeitmarken(array $saetze): string
    {
        $absaetze = [];
        $aktuell = null;
        foreach ($saetze as $s) {
            $text = trim($s['text']);
            if ($text === '') {
                continue;
            }
            if ($aktuell === null || $s['start'] - $aktuell['start'] >= 30) {
                if ($aktuell) {
                    $absaetze[] = $aktuell;
                }
                $aktuell = ['start' => $s['start'], 'text' => $text];
            } else {
                $aktuell['text'] .= ' '.$text;
            }
        }
        if ($aktuell) {
            $absaetze[] = $aktuell;
        }

        return implode("\n\n", array_map(fn ($a) => '['.gmdate($a['start'] >= 3600 ? 'G:i:s' : 'i:s', $a['start']).'] '.$a['text'], $absaetze));
    }
}

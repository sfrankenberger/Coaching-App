<?php

namespace App\Ai;

use App\Models\Tenant;
use App\Support\Bildkarte;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bilder wie auf der Website: ein kurzer Satz in der Stimme der Coachin (Anthropic), eine Bildidee zum Text
 * (Anthropic) und daraus eine flache Vektor-Illustration (OpenAI gpt-image-1, Schluessel audio.openai_key).
 * Zusammengebaut wird es in App\Support\Bildkarte.
 */
class Impulsbild
{
    public function __construct(protected Anthropic $ai, protected CurrentTenant $current) {}

    /** Der OpenAI-Schluessel des Mandanten, derselbe wie fuer Whisper. */
    public static function openaiKey(?Tenant $tenant): ?string
    {
        $key = trim((string) $tenant?->setting('audio.openai_key'));

        return $key !== '' ? $key : null;
    }

    public function illustrationMoeglich(): bool
    {
        return self::openaiKey($this->current->get()) !== null;
    }

    /** Sehr kurze Zeile in der Stimme der Coachin, hoechstens sechs Woerter. */
    public function satz(string $titel, string $text): string
    {
        $r = $this->ai->json(
            "Titel: {$titel}\nText: ".mb_substr(trim(preg_replace('~\s+~u', ' ', strip_tags($text))), 0, 3000)."\n\n"
            .'Schreibe eine sehr kurze Zeile in der Stimme der Coachin, direkt an die Leserin. Maximal sechs Wörter, kein Schlusspunkt '
            .'(ein Fragezeichen ist erlaubt), keine Anführungszeichen, nicht der Titel selbst, Schweizer Schreibweise '
            .'(ss statt scharfem s), aber echte Umlaute, keine Ersatzschreibung mit ue, ae oder oe, kein Gedankenstrich. '
            ."Sie soll den wunden Punkt oder die Wendung des Textes treffen, nicht das Thema zusammenfassen.\n\n"
            .'Antworte nur mit JSON: {"satz":"..."}',
            null,
            150,
        );
        $satz = trim((string) ($r['data']['satz'] ?? ''), " \"«»'.");
        if ($satz === '') {
            throw new RuntimeException('Die KI hat keinen Satz geliefert.');
        }

        return $satz;
    }

    /** Bildidee: eine Figur in sprechender Haltung oder ein bis drei Gegenstaende, zeichenbar, ohne Schrift. */
    public function motiv(string $titel, string $text): string
    {
        $coach = app(Branding::class)->coachName();
        $r = $this->ai->json(
            "Du beschreibst Motive für flache, minimalistische Vektor-Illustrationen für {$coach}, Coachin. "
            ."Die Bildsprache ist ruhig, warm, editorial, erwachsen.\n\n"
            ."Titel: {$titel}\nText: ".mb_substr(trim(preg_replace('~\s+~u', ' ', strip_tags($text))), 0, 3000)."\n\n"
            .'Beschreibe das Motiv in einem einzigen Satz, höchstens 25 Wörter. Erlaubt ist eine einzelne menschliche '
            .'Figur in einer klaren, sprechenden Körperhaltung, oder ein bis drei klar benennbare Gegenstände, oder beides. '
            .'Keine Szene, kein Raum, kein Hintergrund, keine Perspektive, keine Lichtstimmung, keine Gesichter, keine Gruppe. '
            .'Das Motiv kommt aus dem Inhalt genau dieses Textes und ist nicht austauschbar. '
            .'Verboten, ausser der Text handelt ausdrücklich davon: Notizbuch, Stift, Tagebuch, Kaffeetasse, Kerze, Laptop, '
            .'Glühbirne, Zahnrad, Puzzle, Wegweiser, Kompass, Labyrinth, Herz, Wolke, Waage, Bergsteiger, Schmetterling, '
            .'Gehirn, einzelne Hände ohne Körper. '
            .'Das Motiv muss zeichenbar sein: nur Dinge, die man wirklich sehen kann, nichts Unsichtbares, nichts rein '
            .'Symbolisches. Keine Schrift im Bild, also keine beschrifteten Zettel, Schilder, Bücher oder Bildschirme. '
            ."Schreibe das Motiv schlicht hin, ohne Begründung und ohne das Wort symbolisiert.\n\n"
            .'Antworte nur mit JSON: {"motiv":"..."}',
            null,
            200,
        );
        $motiv = trim((string) ($r['data']['motiv'] ?? ''));
        if ($motiv === '') {
            throw new RuntimeException('Die KI hat keine Bildidee geliefert.');
        }

        return $motiv;
    }

    /** Flache Illustration zum Motiv, rechts im Bild, linke Haelfte leer. Gibt PNG-Daten zurueck. */
    public function grafik(string $motiv): string
    {
        $key = self::openaiKey($this->current->get());
        if (! $key) {
            throw new RuntimeException('Kein OpenAI-Schlüssel hinterlegt (Verbindungen, OpenAI-Schlüssel).');
        }
        $p = Bildkarte::palette();
        $prompt = 'Flache, minimalistische Vektor-Illustration, reduziert wie ein Editorial-Icon. '
            .'Nur wenige einfarbige Flächen, keine Verläufe, keine Schattierung, keine Textur, keine Umrisslinien. '
            ."Hintergrund durchgehend cremeweiss {$p['creme']}. Flächen nur in {$p['flaeche']} und {$p['flaeche2']}, "
            ."Menschen erscheinen ausschliesslich als eine einzige geschlossene Silhouette in {$p['tinte']}, ohne Gesicht, "
            .'ohne Hautfarbe, ohne Binnenzeichnung, ohne Muster; Farbe tragen nur einzelne Gegenstände, und ein sparsamer '
            ."Akzent in {$p['akzent']}. Keine anderen Farben. Das Motiv steht rechts im Bild und hält zu jedem Bildrand "
            .'deutlich Abstand, es wird nirgends angeschnitten, die linke Bildhälfte bleibt vollständig leer und cremeweiss. '
            .'Sehr viel Weissraum, ruhige Komposition, kein Text, keine Buchstaben, keine Zahlen, kein Rahmen. Motiv: '.$motiv;

        $r = Http::timeout(240)->withToken($key)->post('https://api.openai.com/v1/images/generations', [
            'model' => 'gpt-image-1', 'prompt' => $prompt, 'size' => '1536x1024', 'quality' => 'medium', 'n' => 1,
        ]);
        if (! $r->successful()) {
            throw new RuntimeException('OpenAI: HTTP '.$r->status().' '.mb_substr((string) ($r->json('error.message') ?? $r->body()), 0, 200));
        }
        $b64 = (string) $r->json('data.0.b64_json');
        $png = $b64 !== '' ? base64_decode($b64, true) : false;
        if (! $png) {
            throw new RuntimeException('OpenAI hat kein Bild geliefert.');
        }

        return $png;
    }
}

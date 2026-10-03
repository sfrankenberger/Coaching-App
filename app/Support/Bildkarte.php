<?php

namespace App\Support;

use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bilder im Stil der Website (1200 x 630): links ein kurzer Satz in Lora kursiv mit Kennzeile und Titel,
 * rechts eine flache Illustration, oder ohne Illustration der Satz mittig. Farben: Palette aus
 * settings.ai.bild (Vorgabe wie die Website), Akzent ist die Hauptfarbe des Mandanten.
 * Die Schriften liegen in resources/fonts (Lora, Oxygen Mono, Cutive Mono, alle SIL Open Font License).
 */
class Bildkarte
{
    public const BREITE = 1200;

    public const HOEHE = 630;

    /** Palette der Website, je Mandant ueberschreibbar in settings.ai.bild. */
    public const PALETTE = ['creme' => '#F3F0E9', 'flaeche' => '#C6D0C3', 'flaeche2' => '#E6E3D8', 'tinte' => '#32312D', 'linie' => '#CBC5B5', 'leise' => '#86816F'];

    /** Textkarte erzeugen und ablegen. Gibt den Pfad auf der Platte 'local' zurueck. */
    public static function erzeugen(string $satz, ?string $unterzeile = null): string
    {
        return self::speichern(self::impuls($satz, null, $unterzeile, null));
    }

    /** PNG-Daten unter tenants/{id}/newsletter ablegen, gibt den Pfad zurueck. */
    public static function speichern(string $png): string
    {
        $pfad = 'tenants/'.app(CurrentTenant::class)->getOrFail()->id.'/newsletter/karte-'.Str::lower(Str::random(16)).'.png';
        Storage::disk('local')->put($pfad, $png);

        return $pfad;
    }

    /** Textkarte ohne Illustration als PNG-Daten. */
    public static function png(string $satz, ?string $unterzeile = null): string
    {
        return self::impuls($satz, null, $unterzeile, null);
    }

    public static function palette(): array
    {
        $eigene = (array) (app(CurrentTenant::class)->get()?->setting('ai.bild') ?? []);

        return array_merge(self::PALETTE, array_filter($eigene, 'is_string')) + ['akzent' => (string) app(Branding::class)->get('primary')];
    }

    /**
     * Das Bild wie auf der Website: Kennzeile (Mono, Versalien, Akzent), Satz (Lora kursiv, letzte Zeile im Akzent),
     * kurzer Strich, Titel (Mono, leise). Mit Illustration steht der Text links und die Grafik rechts,
     * ohne Illustration steht der Text mittig.
     */
    public static function impuls(string $satz, ?string $titel, ?string $kennung, ?string $grafikPng): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            throw new RuntimeException('GD mit FreeType fehlt, Bild nicht möglich.');
        }
        $p = self::palette();
        $W = self::BREITE;
        $H = self::HOEHE;
        $serif = resource_path('fonts/lora-italic.ttf');
        $mono = resource_path('fonts/oxygen-mono.ttf');

        $grafik = $grafikPng ? @imagecreatefromstring($grafikPng) : null;
        $grafik = $grafik instanceof GdImage ? $grafik : null;

        $img = imagecreatetruecolor($W, $H);
        imagesavealpha($img, false);
        // Hintergrund: die Ecke der Illustration, damit sie nahtlos sitzt, sonst Creme
        $bgRgb = $grafik ? self::rgbAt($grafik, 4, 4) : self::rgb($p['creme'], [243, 240, 233]);
        $bg = imagecolorallocate($img, ...$bgRgb);
        imagefilledrectangle($img, 0, 0, $W, $H, $bg);
        $tinte = self::farbe($img, $p['tinte'], [50, 49, 45]);
        $akzent = self::farbe($img, $p['akzent'], [180, 121, 95]);
        $linie = self::farbe($img, $p['linie'], [203, 197, 181]);
        $leise = self::farbe($img, $p['leise'], [134, 129, 111]);

        if ($grafik) {
            self::grafikEinsetzen($img, $grafik, $bgRgb);
            imagedestroy($grafik);
        }

        $satz = trim(preg_replace('~\s+~u', ' ', $satz));
        $satz = str_replace('ß', 'ss', mb_strtoupper(mb_substr($satz, 0, 1)).mb_substr($satz, 1));
        $titel = trim((string) $titel);
        $kennung = mb_strtoupper(trim((string) $kennung));

        $x = 88;
        $breite = $grafik ? 540 : $W - 2 * 120;
        $gr = 54;
        $zeilen = self::umbrechen($satz, $serif, $gr, $breite);
        while (count($zeilen) > 4 && $gr > 34) {
            $gr -= 4;
            $zeilen = self::umbrechen($satz, $serif, $gr, $breite);
        }
        $zh = (int) round($gr * 1.33);
        $tz = $titel !== '' ? array_slice(self::umbrechen($titel, $mono, 19, $breite), 0, 2) : [];
        $hoehe = ($kennung !== '' ? 56 : 0) + count($zeilen) * $zh + 34 + ($tz ? 14 + count($tz) * 30 : 0);
        $y = (int) (($H - $hoehe) / 2) + 26;
        $mitte = ! $grafik;
        $links = fn (string $text, string $font, int $groesse, int $spatium = 0) => $mitte ? (int) (($W - self::breite($text, $font, $groesse, $spatium)) / 2) : $x;

        if ($kennung !== '') {
            self::spationiert($img, 17, $links($kennung, $mono, 17, 5), $y, $akzent, $mono, $kennung, 5);
            $y += 56;
        }
        $y += $gr - 26;
        foreach ($zeilen as $i => $zeile) {
            $f = ($i === count($zeilen) - 1 && count($zeilen) > 1) ? $akzent : $tinte;
            imagettftext($img, $gr, 0, $links($zeile, $serif, $gr), $y, $f, $serif, $zeile);
            $y += $zh;
        }
        $y -= $gr - 26;
        $y -= 26;
        $lx = $mitte ? (int) (($W - 78) / 2) : $x;
        imagefilledrectangle($img, $lx, $y, $lx + 78, $y + 1, $linie);
        $y += 40;
        foreach ($tz as $zeile) {
            imagettftext($img, 19, 0, $links($zeile, $mono, 19), $y + 14, $leise, $mono, $zeile);
            $y += 30;
        }

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** Illustration vom Rand befreien, auf hoechstens 470 x 450 bringen und rechts mittig setzen. */
    protected static function grafikEinsetzen(GdImage $img, GdImage $grafik, array $bgRgb): void
    {
        $hg = imagecolorallocate($grafik, ...$bgRgb);
        $frei = @imagecropauto($grafik, IMG_CROP_THRESHOLD, 0.12, $hg);
        if ($frei instanceof GdImage) {
            $grafik = $frei;
        }
        $gw = imagesx($grafik);
        $gh = imagesy($grafik);
        $sc = min(470 / $gw, 450 / $gh, 1.5);
        $w = max(1, (int) ($gw * $sc));
        $h = max(1, (int) ($gh * $sc));
        imagecopyresampled($img, $grafik, (int) (670 + (470 - $w) / 2), (int) ((self::HOEHE - $h) / 2), 0, 0, $w, $h, $gw, $gh);
    }

    /** Text mit Buchstabenabstand, GD kennt keinen Kerning-Parameter. */
    protected static function spationiert(GdImage $img, int $groesse, int $x, int $y, int $farbe, string $font, string $text, int $spatium): void
    {
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $z) {
            imagettftext($img, $groesse, 0, $x, $y, $farbe, $font, $z);
            $x += self::breite($z, $font, $groesse) + $spatium;
        }
    }

    protected static function breite(string $text, string $font, int $groesse, int $spatium = 0): int
    {
        if ($spatium > 0) {
            $b = 0;
            foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $z) {
                $b += self::breite($z, $font, $groesse) + $spatium;
            }

            return $b - $spatium;
        }
        $box = imagettfbbox($groesse, 0, $font, $text);

        return (int) abs($box[2] - $box[0]);
    }

    /** Woerter auf Zeilen verteilen, die in die Breite passen. */
    protected static function umbrechen(string $text, string $font, int $groesse, int $maxBreite): array
    {
        $zeilen = [];
        $zeile = '';
        foreach (preg_split('~\s+~u', $text) ?: [] as $wort) {
            $probe = $zeile === '' ? $wort : $zeile.' '.$wort;
            if (self::breite($probe, $font, $groesse) > $maxBreite && $zeile !== '') {
                $zeilen[] = $zeile;
                $zeile = $wort;
            } else {
                $zeile = $probe;
            }
        }
        if ($zeile !== '') {
            $zeilen[] = $zeile;
        }

        return $zeilen ?: [''];
    }

    protected static function rgbAt(GdImage $img, int $x, int $y): array
    {
        $c = imagecolorsforindex($img, imagecolorat($img, min($x, imagesx($img) - 1), min($y, imagesy($img) - 1)));

        return [(int) $c['red'], (int) $c['green'], (int) $c['blue']];
    }

    /** #rrggbb oder #rgb als [r, g, b], sonst die Vorgabe. */
    public static function rgb(string $hex, array $vorgabe): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('~^[0-9a-f]{6}$~i', $hex) ? array_map(fn ($h) => (int) hexdec($h), str_split($hex, 2)) : $vorgabe;
    }

    protected static function farbe(GdImage $img, string $hex, array $vorgabe): int
    {
        return imagecolorallocate($img, ...self::rgb($hex, $vorgabe));
    }
}

<?php

namespace App\Support;

use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Eine Textkarte als Bild (1200 x 630): ein Satz in der Leseschrift, eine Unterzeile in Mono,
 * Farben aus dem Branding des Mandanten. Fuer Rundnachricht und Newsletter, wenn kein Foto da ist.
 * Die Schriften liegen in resources/fonts (Lora und Cutive Mono, beide SIL Open Font License).
 */
class Bildkarte
{
    public const BREITE = 1200;

    public const HOEHE = 630;

    /** Erzeugt die Karte und legt sie unter tenants/{id}/newsletter ab. Gibt den Pfad auf der Platte 'local' zurueck. */
    public static function erzeugen(string $satz, ?string $unterzeile = null): string
    {
        $png = self::png($satz, $unterzeile);
        $pfad = 'tenants/'.app(CurrentTenant::class)->getOrFail()->id.'/newsletter/karte-'.Str::lower(Str::random(16)).'.png';
        Storage::disk('local')->put($pfad, $png);

        return $pfad;
    }

    /** Die Karte als PNG-Daten. */
    public static function png(string $satz, ?string $unterzeile = null): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            throw new RuntimeException('GD mit FreeType fehlt, Bildkarte nicht möglich.');
        }
        $br = app(Branding::class);
        $w = self::BREITE;
        $h = self::HOEHE;
        $serif = resource_path('fonts/lora.ttf');
        $mono = resource_path('fonts/cutive-mono.ttf');

        $img = imagecreatetruecolor($w, $h);
        $bg = self::farbe($img, (string) $br->get('bg'), [244, 244, 242]);
        $text = self::farbe($img, (string) $br->get('text'), [38, 39, 43]);
        $primary = self::farbe($img, (string) $br->get('primary'), [74, 108, 140]);
        $muted = self::farbe($img, (string) $br->get('muted'), [138, 140, 148]);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        // Satz: Schriftgroesse so lange verkleinern, bis er in hoechstens fuenf Zeilen passt.
        $satz = trim(preg_replace('~\s+~u', ' ', $satz));
        $maxBreite = $w - 2 * 120;
        $groesse = 60;
        $zeilen = [];
        while ($groesse >= 30) {
            $zeilen = self::umbrechen($satz, $serif, $groesse, $maxBreite);
            if (count($zeilen) <= 5) {
                break;
            }
            $groesse -= 4;
        }
        $zeilenhoehe = (int) round($groesse * 1.4);
        $blockhoehe = count($zeilen) * $zeilenhoehe;
        $oben = (int) (($h - $blockhoehe) / 2) - ($unterzeile ? 12 : 0);

        // Kurzer Strich in der Hauptfarbe ueber dem Satz
        $strich = 56;
        imagefilledrectangle($img, (int) (($w - $strich) / 2), $oben - 34, (int) (($w + $strich) / 2), $oben - 30, $primary);

        $y = $oben + $groesse;
        foreach ($zeilen as $zeile) {
            $box = imagettfbbox($groesse, 0, $serif, $zeile);
            $breite = abs($box[2] - $box[0]);
            imagettftext($img, $groesse, 0, (int) (($w - $breite) / 2), $y, $text, $serif, $zeile);
            $y += $zeilenhoehe;
        }

        if ($unterzeile = trim((string) $unterzeile)) {
            $g = 22;
            $box = imagettfbbox($g, 0, $mono, $unterzeile);
            $breite = abs($box[2] - $box[0]);
            imagettftext($img, $g, 0, (int) (($w - $breite) / 2), $h - 64, $muted, $mono, $unterzeile);
        }

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** Woerter auf Zeilen verteilen, die in die Breite passen. */
    protected static function umbrechen(string $text, string $font, int $groesse, int $maxBreite): array
    {
        $zeilen = [];
        $zeile = '';
        foreach (preg_split('~\s+~u', $text) ?: [] as $wort) {
            $probe = $zeile === '' ? $wort : $zeile.' '.$wort;
            $box = imagettfbbox($groesse, 0, $font, $probe);
            if (abs($box[2] - $box[0]) > $maxBreite && $zeile !== '') {
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

    /** Farbe aus #rrggbb oder #rgb, sonst die Vorgabe. */
    protected static function farbe(\GdImage $img, string $hex, array $vorgabe): int
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        [$r, $g, $b] = preg_match('~^[0-9a-f]{6}$~i', $hex) ? array_map('hexdec', str_split($hex, 2)) : $vorgabe;

        return imagecolorallocate($img, (int) $r, (int) $g, (int) $b);
    }
}

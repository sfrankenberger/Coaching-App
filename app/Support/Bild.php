<?php

namespace App\Support;

use RuntimeException;

/** Bilder mit GD: quadratisch zuschneiden und verkleinern (Profilbilder), Ausrichtung aus EXIF beachten. */
class Bild
{
    /** Gibt ein JPEG (Bytes) mit hoechstens $groesse x $groesse Pixeln zurueck, mittig beschnitten. */
    public static function quadrat(string $daten, int $groesse = 512, int $qualitaet = 84): string
    {
        $bild = @imagecreatefromstring($daten);
        if (! $bild) {
            throw new RuntimeException('Das ist kein Bild, das ich lesen kann.');
        }
        $bild = self::ausrichten($bild, $daten);
        $b = imagesx($bild);
        $h = imagesy($bild);
        $kante = min($b, $h);
        $x = (int) (($b - $kante) / 2);
        $y = (int) (($h - $kante) / 2);
        $groesse = min($groesse, $kante);   // kleine Bilder nicht hochrechnen
        $ziel = imagecreatetruecolor($groesse, $groesse);
        $weiss = imagecolorallocate($ziel, 255, 255, 255);
        imagefill($ziel, 0, 0, $weiss);
        imagecopyresampled($ziel, $bild, 0, 0, $x, $y, $groesse, $groesse, $kante, $kante);
        ob_start();
        imagejpeg($ziel, null, $qualitaet);
        $out = (string) ob_get_clean();
        imagedestroy($bild);
        imagedestroy($ziel);

        return $out;
    }

    /** Handyfotos tragen die Drehung in den EXIF-Daten, GD dreht nicht von selbst. */
    protected static function ausrichten(\GdImage $bild, string $daten): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $bild;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($daten));
        $o = (int) ($exif['Orientation'] ?? 1);
        $gedreht = match ($o) {
            3 => imagerotate($bild, 180, 0),
            6 => imagerotate($bild, -90, 0),
            8 => imagerotate($bild, 90, 0),
            default => null,
        };

        return $gedreht ?: $bild;
    }
}

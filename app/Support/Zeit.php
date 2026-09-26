<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Zeiten und Dauern an einer Stelle formatieren, damit die App ueberall gleich spricht.
 * Zeitpunkte kommen aus den Modellen schon in Ortszeit (Ortszeit-Trait).
 */
class Zeit
{
    /** "Montag, 28. September, 20:00 Uhr" */
    public static function wann(?CarbonInterface $c): string
    {
        return $c ? $c->translatedFormat('l, j. F, H:i').' Uhr' : '';
    }

    /** "Mo 28. Sep, 20:00" */
    public static function wannKurz(?CarbonInterface $c): string
    {
        return $c ? $c->translatedFormat('D j. M, H:i') : '';
    }

    /** "Montag, 28. September" */
    public static function tag(?CarbonInterface $c): string
    {
        return $c ? $c->translatedFormat('l, j. F') : '';
    }

    /** "28. September" */
    public static function datum(?CarbonInterface $c): string
    {
        return $c ? $c->translatedFormat('j. F') : '';
    }

    /** "28. September 2026" */
    public static function datumJahr(?CarbonInterface $c): string
    {
        return $c ? $c->translatedFormat('j. F Y') : '';
    }

    /** "28. Sep 2026" */
    public static function datumKurz(?CarbonInterface $c): string
    {
        return $c ? $c->translatedFormat('j. M Y') : '';
    }

    /** "20:00" */
    public static function uhr(?CarbonInterface $c): string
    {
        return $c ? $c->format('H:i') : '';
    }

    /** "vor 3 Tagen", "in 2 Stunden" */
    public static function relativ(?CarbonInterface $c): string
    {
        return $c ? $c->diffForHumans() : '';
    }

    /** Sekunden als "1 Std. 2 Min." oder "23 Min." */
    public static function dauer(int $sekunden): string
    {
        $min = (int) round($sekunden / 60);
        if ($min < 60) {
            return max(1, $min).' Min.';
        }
        $rest = $min % 60;

        return intdiv($min, 60).' Std.'.($rest ? ' '.$rest.' Min.' : '');
    }

    /**
     * Gespeicherte Dauer-Angaben aus verschiedenen Quellen ("03min", "1h 39min", "58 Min.", "12 min",
     * "1 h 02 min") in eine Form bringen. Freitext ("10 Videos, rund 23 Min.") bleibt, wie er ist.
     */
    public static function dauerLesbar(?string $text): ?string
    {
        $t = trim((string) $text);
        if ($t === '') {
            return null;
        }
        if (preg_match('~^(?:(\d+)\s*(?:h|std\.?|stunden?))?\s*(?:(\d+)\s*(?:min\.?|minuten?))?$~iu', $t, $m) && ($m[1] !== '' || ($m[2] ?? '') !== '')) {
            return self::dauer(((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60);
        }
        if (preg_match('~^(\d{1,2}):(\d{2})(?::(\d{2}))?$~', $t, $m)) {
            return isset($m[3]) ? self::dauer((int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3]) : self::dauer((int) $m[1] * 60 + (int) $m[2]);
        }

        return $t;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Helfer fuer die Suche: Text ohne Tags, Ausschnitt um den Treffer. */
class Suche
{
    public static function text(?string $html): string
    {
        return trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8')));
    }

    /** Ausschnitt von etwa 160 Zeichen um die erste Fundstelle, sonst der Anfang. */
    public static function ausschnitt(?string $text, string $begriff, int $breite = 160): string
    {
        $t = self::text($text);
        if ($t === '') {
            return '';
        }
        $pos = $begriff !== '' ? mb_stripos($t, $begriff) : false;
        if ($pos === false) {
            return Str::limit($t, $breite, ' ...');
        }
        $start = max(0, $pos - (int) ($breite / 3));
        $teil = mb_substr($t, $start, $breite);

        return ($start > 0 ? '... ' : '').trim($teil).(mb_strlen($t) > $start + $breite ? ' ...' : '');
    }
}

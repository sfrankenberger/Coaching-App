<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Reiner Text wird lesbar: Zeilenumbrueche bleiben, Links werden klickbar,
 *
 * @-Erwaehnungen bekommen eine Marke. Alles andere wird entschaerft.
 */
class Textform
{
    /** Ab so vielen Zeichen wird ein Text eingeklappt ("Weiterlesen"). */
    public const LANG = 700;

    public static function lebendig(?string $text): HtmlString
    {
        $t = e(trim((string) $text));
        $t = preg_replace_callback('~(https?://[^\s<]+)~u', function ($m) {
            $url = rtrim($m[1], '.,;:!?)');
            $rest = substr($m[1], strlen($url));
            $kurz = preg_replace('~^https?://(www\.)?~', '', $url);

            return '<a href="'.$url.'" target="_blank" rel="noopener nofollow">'.(mb_strlen($kurz) > 60 ? mb_substr($kurz, 0, 57).'...' : $kurz).'</a>'.$rest;
        }, $t);
        $t = preg_replace('~(^|[\s(])@([\p{L}][\p{L}\-]{1,})~u', '$1<span class="erwaehnt">@$2</span>', $t);

        return new HtmlString(nl2br($t));
    }

    public static function lang(?string $text): bool
    {
        return mb_strlen((string) $text) > self::LANG || substr_count((string) $text, "\n") > 8;
    }
}

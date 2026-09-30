<?php

namespace App\Newsletter;

use App\Models\Kontakt;
use App\Models\NewsletterVersand;
use Illuminate\Support\Str;

/**
 * Text und Links einer Mail fuer eine Empfaengerin aufbereiten: {vorname} und {name} ersetzen,
 * Links auf die Klickzaehlung umbiegen, Absaetze und einfache Links in HTML.
 */
class Vorlage
{
    public static function platzhalter(string $text, ?Kontakt $k): string
    {
        return strtr($text, ['{vorname}' => $k?->vorname() ?? 'du', '{name}' => $k?->name ?? '', '{email}' => $k?->email ?? '']);
    }

    /** Absaetze (Leerzeile), Zeilenumbrueche, URLs und [Text](url) klickbar, alles escaped. */
    public static function html(string $text, ?NewsletterVersand $v = null): string
    {
        $abs = preg_split('~\n\s*\n~', trim($text)) ?: [];
        $out = [];
        foreach ($abs as $a) {
            $a = e(trim($a));
            $a = preg_replace_callback('~\[([^\]]+)\]\((https?://[^\s)]+)\)~', fn ($m) => '<a href="'.self::link($m[2], $v).'" style="color:inherit;font-weight:600;">'.$m[1].'</a>', $a);
            $a = preg_replace_callback('~(?<![">])(https?://[^\s<]+)~', fn ($m) => '<a href="'.self::link($m[1], $v).'" style="color:inherit;">'.$m[1].'</a>', $a);
            $out[] = '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;">'.nl2br($a).'</p>';
        }

        return implode('', $out);
    }

    /** Link ueber die Klickzaehlung fuehren (nur mit Versand, in Test und Webversion direkt). */
    public static function link(string $url, ?NewsletterVersand $v): string
    {
        $url = html_entity_decode($url);
        if (! $v || ! Str::startsWith($url, ['http://', 'https://'])) {
            return e($url);
        }

        return e(route('newsletter.klick', ['token' => $v->token, 'u' => $url]));
    }
}

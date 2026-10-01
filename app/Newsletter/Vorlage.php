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

    /** HTML aus dem Editor: nur erlaubte Tags, Inline-Styles fuer Mailprogramme, Links ueber die Klickzaehlung. */
    public static function rich(string $html, ?NewsletterVersand $v = null, string $absatz = '0 0 14px'): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><s>');
        $html = preg_replace('~\s(on\w+|style|class)="[^"]*"~i', '', $html);
        $html = preg_replace_callback('~<a\b[^>]*href="([^"]+)"[^>]*>~i', fn ($m) => '<a href="'.self::link($m[1], $v).'" style="color:inherit;font-weight:600;">', $html);
        $html = preg_replace('~<a\b(?![^>]*href)[^>]*>~i', '<a>', $html);
        $html = str_replace(['<p>', '<h2>', '<h3>', '<ul>', '<ol>', '<li>'], [
            '<p style="margin:'.$absatz.';font-size:16px;line-height:1.6;">',
            '<h2 style="margin:18px 0 10px;font-size:19px;line-height:1.3;font-weight:600;">',
            '<h3 style="margin:16px 0 8px;font-size:17px;line-height:1.3;font-weight:600;">',
            '<ul style="margin:0 0 14px;padding-left:22px;font-size:16px;line-height:1.6;">',
            '<ol style="margin:0 0 14px;padding-left:22px;font-size:16px;line-height:1.6;">',
            '<li style="margin:0 0 4px;">',
        ], $html);

        return str_replace('<p style="margin:'.$absatz.';font-size:16px;line-height:1.6;"></p>', '', $html);
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

<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\HtmlString;

/**
 * Texte aus KI oder Import sicher anzeigen: erlaubte Tags behalten, alles andere
 * entfernen, Zeitmarken wie "(ab 12:34)" zu Sprungknoepfen ins Video machen.
 */
class Kapitel
{
    protected const ERLAUBT = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a'];

    public static function html(?string $text): HtmlString
    {
        $text = trim((string) $text);
        if ($text === '') {
            return new HtmlString('');
        }

        $istHtml = (bool) preg_match('~</?(p|br|h[1-6]|ul|ol|li|strong|b|em|i|a|div|span|blockquote)\b[^>]*>~i', $text);
        $html = $istHtml ? self::saeubern($text) : nl2br(e($text), false);

        return new HtmlString(self::spruenge($html));
    }

    /** Nur saeubern, ohne Sprungknoepfe (fuer Mails). */
    public static function sauber(?string $text): HtmlString
    {
        $text = trim((string) $text);
        if ($text === '') {
            return new HtmlString('');
        }
        $istHtml = (bool) preg_match('~</?(p|br|h[1-6]|ul|ol|li|strong|b|em|i|a|div|span|blockquote)\b[^>]*>~i', $text);

        return new HtmlString($istHtml ? self::saeubern($text) : nl2br(e($text), false));
    }

    /** Kapitel aus einer Zusammenfassung: Ueberschriften mit "(ab MM:SS)" als [['sekunden' => int, 'titel' => string], ...]. */
    public static function liste(?string $text): array
    {
        $out = [];
        if (! preg_match_all('~<h[2-4][^>]*>(.*?)</h[2-4]>~is', (string) $text, $m)) {
            return $out;
        }
        foreach ($m[1] as $roh) {
            $titel = trim(html_entity_decode(strip_tags($roh), ENT_QUOTES, 'UTF-8'));
            if (preg_match('~^(.*?)\s*\((?:ab|bei|Minute)\s+(\d{1,2}:\d{2}(?::\d{2})?)\)\s*$~u', $titel, $z)) {
                $out[] = ['sekunden' => self::sekunden($z[2]), 'titel' => trim($z[1]) ?: $z[2]];
            }
        }

        return $out;
    }

    /** "12:34" oder "1:02:03" in Sekunden. */
    public static function sekunden(string $zeit): int
    {
        $teile = array_map('intval', explode(':', $zeit));
        $s = 0;
        foreach ($teile as $t) {
            $s = $s * 60 + $t;
        }

        return $s;
    }

    protected static function spruenge(string $html): string
    {
        return preg_replace_callback(
            '~\((?:ab|bei|Minute)\s+(\d{1,2}:\d{2}(?::\d{2})?)\)~u',
            fn ($m) => '<button type="button" class="sprung" data-sprung="'.self::sekunden($m[1]).'"><i class="fa-solid fa-play" style="font-size:9px"></i>'.$m[1].'</button>',
            $html
        );
    }

    protected static function saeubern(string $html): string
    {
        $doc = new DOMDocument;
        $alt = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="wurzel">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($alt);

        $wurzel = $doc->getElementById('wurzel');
        if (! $wurzel) {
            return nl2br(e(strip_tags($html)), false);
        }
        self::knoten($wurzel);

        $out = '';
        foreach ($wurzel->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }

        return $out;
    }

    protected static function knoten(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $c) {
            if ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object'], true)) {
                    $node->removeChild($c);

                    continue;
                }
                self::knoten($c);
                if (! in_array($tag, self::ERLAUBT, true)) {
                    // Tag weg, Inhalt bleibt
                    while ($c->firstChild) {
                        $node->insertBefore($c->firstChild, $c);
                    }
                    $node->removeChild($c);

                    continue;
                }
                foreach (iterator_to_array($c->attributes) as $a) {
                    $ok = $tag === 'a' && $a->name === 'href' && preg_match('~^(https?:|mailto:|/|#)~i', trim($a->value));
                    if (! $ok) {
                        $c->removeAttribute($a->name);
                    }
                }
                if ($tag === 'a') {
                    $c->setAttribute('target', '_blank');
                    $c->setAttribute('rel', 'noopener');
                }
            }
        }
    }
}

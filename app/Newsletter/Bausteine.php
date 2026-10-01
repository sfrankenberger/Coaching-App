<?php

namespace App\Newsletter;

use App\Models\Kontakt;
use App\Models\NewsletterVersand;
use App\Models\Offer;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Der Baukasten fuer Newsletter und Serienmails: Bausteine im Coach-Bereich (Filament Builder),
 * dieselben Bausteine als Mail-HTML (Tabellen, Inline-Styles, Klickzaehlung) und als Klartext.
 *
 * Ein Baustein ist ['type' => 'text', 'data' => [...]]. Arten: ueberschrift, text, bild, knopf, trenner, zitat, kasten, angebot.
 */
class Bausteine
{
    public const ARTEN = ['ueberschrift', 'text', 'bild', 'knopf', 'trenner', 'zitat', 'kasten', 'angebot'];

    /** Das Formularfeld fuer den Coach-Bereich. */
    public static function feld(string $name = 'bloecke'): Builder
    {
        $ordner = fn () => 'tenants/'.app(CurrentTenant::class)->id().'/newsletter';

        return Builder::make($name)->label('Inhalt')
            ->blocks([
                Block::make('ueberschrift')->label('Überschrift')->icon(Heroicon::OutlinedH1)->schema([
                    TextInput::make('text')->label('Überschrift')->required()->maxLength(200)->live(onBlur: true)->columnSpan(2),
                    Select::make('groesse')->label('Grösse')->options(['gross' => 'gross', 'klein' => 'klein'])->default('gross')->selectablePlaceholder(false)->live(),
                ])->columns(3),
                Block::make('text')->label('Text')->icon(Heroicon::OutlinedBars3BottomLeft)->schema([
                    RichEditor::make('html')->label('')->required()->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'h3', 'undo', 'redo'])->live(debounce: 1200)
                        ->helperText('{vorname} und {name} werden ersetzt. Links über das Kettensymbol.'),
                ]),
                Block::make('bild')->label('Bild')->icon(Heroicon::OutlinedPhoto)->schema([
                    FileUpload::make('datei')->label('Bild hochladen')->image()->disk('local')->directory($ordner)->maxSize(8192)
                        ->imageResizeMode('contain')->imageResizeTargetWidth('1200')->imageResizeTargetHeight('1200')
                        ->getUploadedFileNameForStorageUsing(fn ($file) => Str::lower(Str::random(20)).'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'))
                        ->live()->columnSpanFull(),
                    TextInput::make('url')->label('oder Bild-Adresse (https://...)')->url()->maxLength(500)->live(onBlur: true),
                    TextInput::make('link')->label('Klick führt zu')->url()->maxLength(500)->live(onBlur: true),
                    TextInput::make('alt')->label('Bildbeschreibung (für Vorleser)')->maxLength(150),
                    Select::make('breite')->label('Breite')->options(['voll' => 'volle Breite', 'klein' => 'klein, mittig'])->default('voll')->selectablePlaceholder(false)->live(),
                ])->columns(2),
                Block::make('knopf')->label('Knopf')->icon(Heroicon::OutlinedCursorArrowRays)->schema([
                    TextInput::make('text')->label('Beschriftung')->required()->maxLength(80)->live(onBlur: true),
                    TextInput::make('url')->label('Führt zu')->required()->url()->maxLength(500)->live(onBlur: true),
                    Select::make('stil')->label('Stil')->options(['voll' => 'gefüllt', 'leise' => 'nur Rahmen'])->default('voll')->selectablePlaceholder(false)->live(),
                    Select::make('ausrichtung')->label('Ausrichtung')->options(['mitte' => 'mittig', 'links' => 'links'])->default('mitte')->selectablePlaceholder(false)->live(),
                ])->columns(2),
                Block::make('trenner')->label('Trenner')->icon(Heroicon::OutlinedMinus)->schema([
                    Select::make('art')->label('Art')->options(['linie' => 'feine Linie', 'abstand' => 'nur Abstand'])->default('linie')->selectablePlaceholder(false)->live(),
                ]),
                Block::make('zitat')->label('Zitat')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                    Textarea::make('text')->label('Zitat')->required()->rows(3)->live(onBlur: true),
                    TextInput::make('von')->label('Von')->maxLength(120)->live(onBlur: true),
                ]),
                Block::make('kasten')->label('Kasten')->icon(Heroicon::OutlinedRectangleStack)->schema([
                    TextInput::make('titel')->label('Titel')->maxLength(150)->live(onBlur: true),
                    RichEditor::make('html')->label('Text')->required()->toolbarButtons(['bold', 'italic', 'bulletList', 'link', 'undo', 'redo'])->live(debounce: 1200),
                ]),
                Block::make('angebot')->label('Angebot')->icon(Heroicon::OutlinedShoppingBag)->schema([
                    Select::make('offer_id')->label('Angebot aus der App')->required()->options(fn () => Offer::query()->orderBy('title')->pluck('title', 'id')->all())->searchable()->live(),
                    TextInput::make('knopf_text')->label('Knopf')->default('Mehr erfahren')->maxLength(80)->live(onBlur: true),
                ])->columns(2),
            ])
            ->addActionLabel('Baustein hinzufügen')->blockPickerColumns(2)->blockNumbers(false)
            ->collapsible()->cloneable()->reorderableWithButtons()->live();
    }

    /** Alte Felder (Headline, Text, Bild, Knopf) als Bausteine, fuer Entwuerfe aus der Zeit vor dem Baukasten und aus den KI-Werkzeugen. */
    public static function ausAlt(?string $bild, ?string $titel, ?string $text, ?string $knopfText, ?string $knopfUrl): array
    {
        $b = [];
        if (filled($bild)) {
            $b[] = ['type' => 'bild', 'data' => ['url' => $bild, 'breite' => 'voll']];
        }
        if (filled($titel)) {
            $b[] = ['type' => 'ueberschrift', 'data' => ['text' => $titel, 'groesse' => 'gross']];
        }
        if (filled($text)) {
            $b[] = ['type' => 'text', 'data' => ['html' => self::textZuHtml($text)]];
        }
        if (filled($knopfText) && filled($knopfUrl)) {
            $b[] = ['type' => 'knopf', 'data' => ['text' => $knopfText, 'url' => $knopfUrl, 'stil' => 'voll', 'ausrichtung' => 'mitte']];
        }

        return $b;
    }

    /** Klartext mit Leerzeilen und [Text](url) als Absaetze mit Links. */
    public static function textZuHtml(string $text): string
    {
        $abs = preg_split('~\n\s*\n~', trim($text)) ?: [];
        $out = [];
        foreach ($abs as $a) {
            $a = e(trim($a));
            $a = preg_replace_callback('~\[([^\]]+)\]\((https?://[^\s)]+)\)~', fn ($m) => '<a href="'.$m[2].'">'.$m[1].'</a>', $a);
            $out[] = '<p>'.nl2br($a).'</p>';
        }

        return implode('', $out);
    }

    /** Bausteine als Klartext (Liste, Suche, Vorschautext, Werkzeuge). */
    public static function text(?array $bloecke): string
    {
        $t = [];
        foreach ((array) $bloecke as $b) {
            $d = (array) ($b['data'] ?? []);
            $t[] = match ($b['type'] ?? '') {
                'ueberschrift' => (string) ($d['text'] ?? ''),
                'text', 'kasten' => trim(($d['titel'] ?? '') !== '' ? ($d['titel'] ?? '')."\n" : '').self::htmlZuText(self::alsHtml($d['html'] ?? '')),
                'zitat' => '"'.($d['text'] ?? '').'"'.(filled($d['von'] ?? null) ? ' ('.$d['von'].')' : ''),
                'knopf' => ($d['text'] ?? '').': '.($d['url'] ?? ''),
                'angebot' => (string) (Offer::find((int) ($d['offer_id'] ?? 0))?->title ?? ''),
                default => '',
            };
        }

        return trim(implode("\n\n", array_filter(array_map('trim', $t))));
    }

    /** Editor-Inhalt als HTML: im Formular liegt er waehrend des Bearbeitens als Dokument (Array) vor, gespeichert als HTML. */
    public static function alsHtml(mixed $wert): string
    {
        if (is_array($wert)) {
            try {
                return RichContentRenderer::make($wert)->toUnsafeHtml();
            } catch (\Throwable) {
                return '';
            }
        }

        return (string) $wert;
    }

    public static function htmlZuText(string $html): string
    {
        $html = preg_replace('~<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>~is', '$2 ($1)', $html);
        $html = preg_replace('~</(p|div|h[1-6]|li)>|<br\s*/?>~i', "\n", $html);

        return trim(preg_replace("~\n{3,}~", "\n\n", html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
    }

    /** Erste Ueberschrift, fuer Liste und Werkzeuge. */
    public static function titel(?array $bloecke): ?string
    {
        foreach ((array) $bloecke as $b) {
            if (($b['type'] ?? '') === 'ueberschrift' && filled($b['data']['text'] ?? null)) {
                return $b['data']['text'];
            }
        }

        return null;
    }

    /** Bausteine als Mail-HTML. $v = Versand (Klickzaehlung), null in Test, Vorschau und Webversion. */
    public static function html(?array $bloecke, ?Kontakt $k, ?NewsletterVersand $v = null): string
    {
        $br = app(Branding::class);
        $primary = $br->get('primary');
        $kontrast = $br->get('primary_contrast');
        $muted = $br->get('muted');
        $linie = $br->get('card_border');
        $heading = $br->get('font_heading');
        $pl = fn (mixed $s) => Vorlage::platzhalter(self::alsHtml($s), $k);
        $out = [];
        foreach ((array) $bloecke as $b) {
            $d = (array) ($b['data'] ?? []);
            switch ($b['type'] ?? '') {
                case 'ueberschrift':
                    $gross = ($d['groesse'] ?? 'gross') === 'gross';
                    $out[] = '<h'.($gross ? 1 : 2).' style="margin:0 0 14px;font-size:'.($gross ? 24 : 19).'px;line-height:1.3;font-weight:400;font-family:'.e($heading).';">'.e($pl($d['text'] ?? '')).'</h'.($gross ? 1 : 2).'>';
                    break;
                case 'text':
                    $out[] = Vorlage::rich($pl($d['html'] ?? ''), $v);
                    break;
                case 'bild':
                    $src = self::bildUrl($d);
                    if (! $src) {
                        break;
                    }
                    $klein = ($d['breite'] ?? 'voll') === 'klein';
                    $img = '<img src="'.e($src).'" alt="'.e($d['alt'] ?? '').'" width="'.($klein ? 280 : 472).'" style="width:100%;max-width:'.($klein ? 280 : 472).'px;height:auto;border-radius:12px;display:block;margin:0 auto;border:0;">';
                    if (filled($d['link'] ?? null)) {
                        $img = '<a href="'.Vorlage::link($d['link'], $v).'" style="display:block;">'.$img.'</a>';
                    }
                    $out[] = '<div style="margin:0 0 18px;text-align:center;">'.$img.'</div>';
                    break;
                case 'knopf':
                    if (! filled($d['text'] ?? null) || ! filled($d['url'] ?? null)) {
                        break;
                    }
                    $stil = ($d['stil'] ?? 'voll') === 'leise'
                        ? 'display:inline-block;border:1px solid '.$primary.';color:'.$primary.';text-decoration:none;font-weight:600;font-size:16px;padding:12px 26px;border-radius:999px;'
                        : 'display:inline-block;background:'.$primary.';color:'.$kontrast.';text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;';
                    $out[] = '<p style="margin:6px 0 18px;text-align:'.(($d['ausrichtung'] ?? 'mitte') === 'links' ? 'left' : 'center').';"><a href="'.Vorlage::link($d['url'], $v).'" style="'.$stil.'">'.e($pl($d['text'])).'</a></p>';
                    break;
                case 'trenner':
                    $out[] = ($d['art'] ?? 'linie') === 'abstand'
                        ? '<div style="height:22px;line-height:22px;font-size:0;">&nbsp;</div>'
                        : '<hr style="border:0;border-top:1px solid '.$linie.';margin:6px 0 20px;">';
                    break;
                case 'zitat':
                    $out[] = '<blockquote style="margin:0 0 18px;padding:4px 0 4px 16px;border-left:3px solid '.$primary.';font-family:'.e($heading).';font-size:19px;line-height:1.45;font-style:italic;">'.nl2br(e($pl($d['text'] ?? '')))
                        .(filled($d['von'] ?? null) ? '<div style="margin-top:6px;font-size:13px;font-style:normal;color:'.$muted.';">'.e($d['von']).'</div>' : '').'</blockquote>';
                    break;
                case 'kasten':
                    $out[] = '<div style="margin:0 0 18px;padding:16px 18px;background:'.$br->get('bg').';border-radius:12px;">'
                        .(filled($d['titel'] ?? null) ? '<div style="margin:0 0 6px;font-weight:600;font-size:15px;">'.e($pl($d['titel'])).'</div>' : '')
                        .Vorlage::rich($pl($d['html'] ?? ''), $v, '0 0 8px').'</div>';
                    break;
                case 'angebot':
                    $o = Offer::find((int) ($d['offer_id'] ?? 0));
                    if (! $o) {
                        break;
                    }
                    $preise = $o->preise();
                    $preis = $o->is_free ? 'Kostenlos' : (isset($preise['CHF']) ? number_format((float) $preise['CHF'], 2, '.', "'").' CHF' : (($w = array_key_first($preise)) ? number_format((float) $preise[$w], 2, '.', "'").' '.$w : ''));
                    $bild = $o->settings['bild_url'] ?? null;
                    $out[] = '<div style="margin:0 0 18px;border:1px solid '.$linie.';border-radius:14px;padding:18px;">'
                        .($bild ? '<img src="'.e($bild).'" alt="" width="436" style="width:100%;max-width:436px;height:auto;border-radius:10px;display:block;margin:0 0 12px;border:0;">' : '')
                        .'<div style="font-family:'.e($heading).';font-size:20px;line-height:1.3;margin:0 0 6px;">'.e($o->title).'</div>'
                        .(filled($o->settings['teaser'] ?? null) ? '<p style="margin:0 0 10px;font-size:15px;line-height:1.5;">'.e($o->settings['teaser']).'</p>' : '')
                        .($preis !== '' ? '<div style="font-weight:600;margin:0 0 12px;">'.e($preis).'</div>' : '')
                        .'<a href="'.Vorlage::link($o->kaufUrl('newsletter'), $v).'" style="display:inline-block;background:'.$primary.';color:'.$kontrast.';text-decoration:none;font-weight:600;font-size:15px;padding:11px 22px;border-radius:999px;">'.e($d['knopf_text'] ?? 'Mehr erfahren').'</a></div>';
                    break;
            }
        }

        return implode('', $out);
    }

    /** Adresse eines Bild-Bausteins: hochgeladene Datei ueber die App, sonst die eingetragene Adresse. */
    public static function bildUrl(array $d): ?string
    {
        $datei = $d['datei'] ?? null;
        if (is_array($datei)) {
            $datei = reset($datei) ?: null;
        }
        if (is_string($datei) && $datei !== '') {
            return route('newsletter.bild', ['datei' => basename($datei)]);
        }

        return filled($d['url'] ?? null) ? (string) $d['url'] : null;
    }
}

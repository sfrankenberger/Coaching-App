<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Liest das Branding des aktuellen Mandanten (tenants.branding) und liefert
 * daraus CSS-Variablen, Manifest-Daten und Einzelwerte mit neutralen Vorgaben.
 * Alles Mandantenspezifische (Farben, Schriften, Name, Logo) kommt aus der
 * Datenbank, nie aus dem Code.
 */
class Branding
{
    /** Neutrale Vorgaben, wenn ein Mandant nichts hinterlegt hat. */
    public const DEFAULTS = [
        'app_name' => null,             // null = tenants.name
        'short_name' => null,           // fuer den Startbildschirm
        'logo_url' => null,
        'icon_url' => null,             // 512x512, PWA und Favicon
        'icons' => [],                  // [['src' => ..., 'sizes' => '192x192', 'type' => 'image/png'], ...]
        'primary' => '#4A6C8C',
        'primary_contrast' => '#FFFFFF',
        'text' => '#26272B',
        'text_soft' => '#5C5E66',
        'muted' => '#8A8C94',
        'bg' => '#F4F4F2',
        'card_bg' => '#FFFFFF',
        'card_border' => '#E2E2DD',
        'success' => '#4F7F5C',
        'danger' => '#B24A45',
        'font_heading' => 'Georgia, "Times New Roman", serif',
        'font_body' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'font_url' => null,             // z. B. Google-Fonts-Link, optional
        'radius' => 16,
        'card_padding_y' => 14,
        'card_padding_x' => 16,
        'gap' => 8,
        'page_width' => 720,
        'font_scale' => [11, 12.5, 13.5, 15.5, 17, 20, 26, 32],
    ];

    public function __construct(protected CurrentTenant $current) {}

    public function tenant(): ?Tenant
    {
        return $this->current->get();
    }

    /** Einzelwert mit Vorgabe. */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->tenant()?->branding, $key);

        return $value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function appName(): string
    {
        return (string) ($this->get('app_name') ?? $this->tenant()?->name ?? config('app.name'));
    }

    public function shortName(): string
    {
        return (string) ($this->get('short_name') ?? $this->appName());
    }

    /**
     * CSS-Variablen fuer die Seitenhuelle. Werden inline in den <head> geschrieben,
     * damit die Farben ohne eigenen Build je Mandant stimmen.
     */
    public function cssVariables(): string
    {
        $scale = $this->get('font_scale');
        $scale = is_array($scale) && count($scale) === 8 ? $scale : self::DEFAULTS['font_scale'];

        $vars = [
            '--c-primary' => $this->get('primary'),
            '--c-primary-contrast' => $this->get('primary_contrast'),
            '--c-text' => $this->get('text'),
            '--c-text-soft' => $this->get('text_soft'),
            '--c-muted' => $this->get('muted'),
            '--c-bg' => $this->get('bg'),
            '--c-card' => $this->get('card_bg'),
            '--c-card-border' => $this->get('card_border'),
            '--c-success' => $this->get('success'),
            '--c-danger' => $this->get('danger'),
            '--font-heading' => $this->get('font_heading'),
            '--font-body' => $this->get('font_body'),
            '--radius' => $this->px($this->get('radius')),
            '--card-py' => $this->px($this->get('card_padding_y')),
            '--card-px' => $this->px($this->get('card_padding_x')),
            '--gap' => $this->px($this->get('gap')),
            '--page-width' => $this->px($this->get('page_width')),
        ];

        foreach (['xs', 'sm', 'md', 'base', 'lg', 'xl', '2xl', '3xl'] as $i => $name) {
            $vars['--fs-'.$name] = $this->px($scale[$i]);
        }

        $out = '';
        foreach ($vars as $name => $value) {
            $out .= $name.':'.$this->sanitize((string) $value).';';
        }

        return ':root{'.$out.'}';
    }

    /** Daten fuer das PWA-Manifest je Mandant. */
    public function manifest(): array
    {
        $icons = $this->get('icons');
        if (! is_array($icons) || $icons === []) {
            $icon = $this->get('icon_url');
            $icons = $icon ? [['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']] : [];
        }

        return [
            'name' => $this->appName(),
            'short_name' => $this->shortName(),
            'start_url' => '/?quelle=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => $this->get('bg'),
            'theme_color' => $this->get('card_bg'),
            'lang' => str_replace('_', '-', $this->tenant()?->locale ?? 'de-CH'),
            'icons' => $icons,
        ];
    }

    protected function px(mixed $value): string
    {
        return is_numeric($value) ? $value.'px' : (string) $value;
    }

    /** Nur, was in einer CSS-Deklaration stehen darf: keine Klammern zu, keine Semikola. */
    protected function sanitize(string $value): string
    {
        return str_replace([';', '}', '{', '<', '>'], '', $value);
    }
}

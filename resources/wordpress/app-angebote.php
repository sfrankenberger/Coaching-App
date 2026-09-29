<?php

/**
 * Plugin Name: Coaching-App - Angebote
 * Description: Zeigt Angebote aus der Coaching-App auf der Website. Shortcodes: [app_kaufen slug="..." text="Jetzt dabei sein" ref="herbst-webinar"] (Knopf), [app_angebot slug="..."] (Karte mit Preis und Knopf), [app_angebote] (alle sichtbaren Angebote). Die Daten kommen aus /api/angebote der App, zehn Minuten zwischengespeichert. Adresse der App unter Einstellungen > Allgemein > "Coaching-App Adresse" (Option coaching_app_url).
 * Version: 1.0
 */
if (! defined('ABSPATH')) {
    exit;
}

function capp_url()
{
    return rtrim((string) get_option('coaching_app_url', ''), '/');
}

/** Alle sichtbaren Angebote (Liste) oder eines (slug), aus dem Cache. */
function capp_angebote($slug = '')
{
    $basis = capp_url();
    if ($basis === '') {
        return $slug ? null : [];
    }
    $key = 'capp_'.md5($basis.'|'.$slug);
    $c = get_transient($key);
    if ($c !== false) {
        return $c;
    }
    $r = wp_remote_get($basis.'/api/angebote'.($slug ? '/'.rawurlencode($slug) : ''), ['timeout' => 8, 'headers' => ['Accept' => 'application/json']]);
    if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
        set_transient($key, $slug ? null : [], 2 * MINUTE_IN_SECONDS);

        return $slug ? null : [];
    }
    $j = json_decode(wp_remote_retrieve_body($r), true);
    $out = $slug ? (is_array($j) ? $j : null) : (array) ($j['angebote'] ?? []);
    set_transient($key, $out, 10 * MINUTE_IN_SECONDS);

    return $out;
}

function capp_preis($a)
{
    if (! empty($a['gratis'])) {
        return 'Kostenlos';
    }
    $p = (array) ($a['preise'] ?? []);
    if (! $p) {
        return '';
    }
    $w = isset($p['CHF']) ? 'CHF' : array_key_first($p);
    $t = number_format((float) $p[$w], 2, '.', "'").' '.$w;
    $r = (array) ($a['preise_regulaer'] ?? []);
    if (! empty($r[$w]) && (float) $r[$w] > (float) $p[$w]) {
        $t = '<s style="opacity:.6;font-weight:400">'.number_format((float) $r[$w], 2, '.', "'").' '.$w.'</s> '.$t;
    }

    return $t;
}

function capp_link($a, $ref = '')
{
    $u = (string) ($a['kaufen'] ?? '');

    return $ref !== '' ? add_query_arg('ref', rawurlencode($ref), $u) : $u;
}

function capp_css()
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;

    return '<style>.capp-knopf{display:inline-block;background:var(--e-global-color-primary,#B5654A);color:#fff;text-decoration:none;font-weight:600;padding:13px 26px;border-radius:999px}.capp-knopf:hover{opacity:.9;color:#fff}.capp-karte{border:1px solid var(--e-global-color-lealinie,#E2DDD1);border-radius:18px;padding:22px;background:var(--e-global-color-leaflaeche,#FFFDF9);margin:0 0 18px}.capp-karte img{width:100%;border-radius:12px;margin:0 0 12px;aspect-ratio:16/9;object-fit:cover}.capp-karte h3{margin:0 0 6px;font-size:1.3rem}.capp-karte p{margin:0 0 12px;line-height:1.6}.capp-preis{font-size:1.25rem;font-weight:600;margin:0 0 12px}.capp-liste{display:grid;gap:18px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}</style>';
}

add_shortcode('app_kaufen', function ($atts) {
    $a = shortcode_atts(['slug' => '', 'text' => 'Jetzt dabei sein', 'ref' => ''], $atts);
    $ang = capp_angebote($a['slug']);
    if (! $ang) {
        return '';
    }

    return capp_css().'<a class="capp-knopf" href="'.esc_url(capp_link($ang, $a['ref'])).'">'.esc_html($a['text']).'</a>';
});

function capp_karte($ang, $ref = '', $text = 'Jetzt dabei sein')
{
    $h = '<div class="capp-karte">';
    if (! empty($ang['bild'])) {
        $h .= '<img src="'.esc_url($ang['bild']).'" alt="">';
    }
    $h .= '<h3>'.esc_html($ang['titel']).'</h3>';
    if (! empty($ang['teaser'])) {
        $h .= '<p>'.esc_html($ang['teaser']).'</p>';
    }
    $h .= '<div class="capp-preis">'.wp_kses_post(capp_preis($ang)).'</div>';
    $h .= '<a class="capp-knopf" href="'.esc_url(capp_link($ang, $ref)).'">'.esc_html($text).'</a></div>';

    return $h;
}

add_shortcode('app_angebot', function ($atts) {
    $a = shortcode_atts(['slug' => '', 'ref' => '', 'text' => 'Jetzt dabei sein'], $atts);
    $ang = capp_angebote($a['slug']);

    return $ang ? capp_css().capp_karte($ang, $a['ref'], $a['text']) : '';
});

add_shortcode('app_angebote', function ($atts) {
    $a = shortcode_atts(['ref' => '', 'text' => 'Mehr erfahren'], $atts);
    $liste = capp_angebote();
    if (! $liste) {
        return '';
    }
    $h = capp_css().'<div class="capp-liste">';
    foreach ($liste as $ang) {
        $h .= capp_karte($ang, $a['ref'], $a['text']);
    }

    return $h.'</div>';
});

/* Einstellung: Adresse der App unter Einstellungen > Allgemein */
add_action('admin_init', function () {
    register_setting('general', 'coaching_app_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
    add_settings_field('coaching_app_url', 'Coaching-App Adresse', function () {
        echo '<input type="url" name="coaching_app_url" value="'.esc_attr(get_option('coaching_app_url', '')).'" class="regular-text" placeholder="https://app.example.ch">';
    }, 'general');
});

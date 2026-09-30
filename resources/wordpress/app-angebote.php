<?php

/**
 * Plugin Name: Coaching-App - Angebote
 * Description: Zeigt Angebote aus der Coaching-App auf der Website. Shortcodes: [app_kaufen slug="..." text="Jetzt dabei sein" ref="herbst-webinar"] (Knopf), [app_angebot slug="..."] (Karte mit Preis und Knopf), [app_angebote] (alle sichtbaren Angebote), [app_anmelden tag="newsletter" text="Anmelden" sofort="0"] (Anmeldeformular fuer Newsletter, Freebie oder Veranstaltung; sofort=1 ohne Bestaetigungsmail). Die Daten kommen aus /api/angebote der App, zehn Minuten zwischengespeichert. Adresse der App unter Einstellungen > Allgemein > "Coaching-App Adresse" (Option coaching_app_url).
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
    register_setting('general', 'coaching_app_token', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    add_settings_field('coaching_app_token', 'Coaching-App Schlüssel', function () {
        echo '<input type="password" name="coaching_app_token" value="'.esc_attr(get_option('coaching_app_token', '')).'" class="regular-text" autocomplete="off"> <span class="description">Schlüssel aus dem Profil der Coachin (Schlüssel für Verbindungen). Damit kann die Website beim Veröffentlichen einen Newsletter in der App anlegen.</span>';
    }, 'general');
    register_setting('general', 'coaching_app_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
    add_settings_field('coaching_app_url', 'Coaching-App Adresse', function () {
        echo '<input type="url" name="coaching_app_url" value="'.esc_attr(get_option('coaching_app_url', '')).'" class="regular-text" placeholder="https://app.example.ch">';
    }, 'general');
});

/** Anmeldeformular: schickt an die App (POST /newsletter/anmelden), die App bestaetigt per Mail und leitet hierher zurueck (?newsletter=postfach|dabei). */
add_shortcode('app_anmelden', function ($atts) {
    $a = shortcode_atts(['tag' => 'newsletter', 'text' => 'Anmelden', 'sofort' => '0', 'name' => '1', 'hinweis' => 'Kein Spam, abmelden geht mit einem Klick.'], $atts);
    $basis = capp_url();
    if ($basis === '') {
        return '';
    }
    $css = capp_css();
    $stand = isset($_GET['newsletter']) ? sanitize_key($_GET['newsletter']) : '';
    if ($stand === 'postfach') {
        return $css.'<div class="capp-karte"><p><strong>Fast geschafft.</strong> Schau in dein Postfach und bestätige deine Anmeldung mit einem Klick. Auch im Spam nachsehen.</p></div>';
    }
    if ($stand === 'dabei') {
        return $css.'<div class="capp-karte"><p><strong>Du bist dabei.</strong> Danke für dein Vertrauen.</p></div>';
    }
    $zurueck = esc_url((is_ssl() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'], '?'));
    $h = $css.'<form method="post" action="'.esc_url($basis.'/newsletter/anmelden').'" class="capp-karte capp-form">';
    $h .= '<input type="hidden" name="tag" value="'.esc_attr($a['tag']).'"><input type="hidden" name="zurueck" value="'.$zurueck.'"><input type="hidden" name="herkunft" value="website:'.esc_attr(sanitize_title(get_the_title() ?: 'seite')).'">';
    if ($a['sofort'] === '1') {
        $h .= '<input type="hidden" name="sofort" value="1">';
    }
    $h .= '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">';
    if ($a['name'] === '1') {
        $h .= '<p><label>Dein Vorname<br><input type="text" name="name" maxlength="120" autocomplete="given-name" style="width:100%"></label></p>';
    }
    $h .= '<p><label>Deine E-Mail<br><input type="email" name="email" required maxlength="190" autocomplete="email" style="width:100%"></label></p>';
    $h .= '<p><label><input type="checkbox" name="einwilligung" value="1" required> Ja, schreibt mir. Ich weiss, dass ich mich jederzeit abmelden kann.</label></p>';
    $h .= '<p><button type="submit" class="capp-knopf">'.esc_html($a['text']).'</button></p>';
    if ($a['hinweis'] !== '') {
        $h .= '<p style="font-size:.85em;opacity:.7">'.esc_html($a['hinweis']).'</p>';
    }

    return $h.'</form>';
});

/* ---------- Newsletter aus einem Beitrag: Empfaengerinnen beim Veroeffentlichen waehlen ---------- */

function capp_api($pfad, $methode = 'GET', $daten = null)
{
    $basis = capp_url();
    $token = (string) get_option('coaching_app_token', '');
    if ($basis === '' || $token === '') {
        return null;
    }
    $args = ['timeout' => 15, 'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token, 'Content-Type' => 'application/json'], 'method' => $methode];
    if ($daten !== null) {
        $args['body'] = wp_json_encode($daten);
    }
    $r = wp_remote_request($basis.'/api/v1'.$pfad, $args);
    if (is_wp_error($r)) {
        return ['fehler' => $r->get_error_message()];
    }
    $j = json_decode(wp_remote_retrieve_body($r), true);
    $code = (int) wp_remote_retrieve_response_code($r);

    return is_array($j) ? $j + ['http' => $code] : ['fehler' => 'HTTP '.$code, 'http' => $code];
}

add_action('add_meta_boxes', function () {
    if (capp_url() === '' || get_option('coaching_app_token', '') === '') {
        return;
    }
    add_meta_box('capp_newsletter', 'Newsletter aus der Coaching-App', function ($post) {
        $tags = get_transient('capp_tags');
        if ($tags === false) {
            $r = capp_api('/newsletter/tags');
            $tags = is_array($r) && isset($r['tags']) ? $r : [];
            set_transient('capp_tags', $tags, 10 * MINUTE_IN_SECONDS);
        }
        $gesendet = get_post_meta($post->ID, '_capp_newsletter_id', true);
        $stand = get_post_meta($post->ID, '_capp_newsletter_stand', true);
        $wahl = (array) get_post_meta($post->ID, '_capp_newsletter_tags', true);
        $modus = get_post_meta($post->ID, '_capp_newsletter_modus', true) ?: 'nein';
        wp_nonce_field('capp_newsletter', 'capp_newsletter_nonce');
        if ($gesendet) {
            echo '<p><strong>Newsletter #'.esc_html($gesendet).'</strong> in der App angelegt'.($stand ? ' ('.esc_html($stand).')' : '').'. <a href="'.esc_url(capp_url().'/coach/newsletter/'.$gesendet.'/bearbeiten').'" target="_blank" rel="noopener">In der App öffnen</a></p>';
        }
        echo '<p><label><input type="radio" name="capp_modus" value="nein" '.checked($modus, 'nein', false).'> Nicht als Newsletter schicken</label><br>';
        echo '<label><input type="radio" name="capp_modus" value="entwurf" '.checked($modus, 'entwurf', false).'> Beim Veröffentlichen Entwurf in der App anlegen (dort prüfen und senden)</label><br>';
        echo '<label><input type="radio" name="capp_modus" value="senden" '.checked($modus, 'senden', false).'> Beim Veröffentlichen sofort senden</label></p>';
        echo '<p><strong>An wen</strong> (leer: alle '.(int) ($tags['bestaetigt'] ?? 0).' bestätigten Kontakte)</p>';
        foreach ((array) ($tags['tags'] ?? []) as $t) {
            echo '<label style="display:block"><input type="checkbox" name="capp_tags[]" value="'.esc_attr($t['tag']).'" '.checked(in_array($t['tag'], $wahl, true), true, false).'> '.esc_html($t['tag']).' <span style="opacity:.6">('.(int) $t['anzahl'].')</span></label>';
        }
        if (empty($tags['tags'])) {
            echo '<p class="description">Keine Tags gefunden. Stimmen Adresse und Schlüssel unter Einstellungen, Allgemein?</p>';
        }
        echo '<p class="description">Bild, Titel, Auszug und Link zum Beitrag gehen mit. Gesendet wird höchstens einmal je Beitrag.</p>';
    }, 'post', 'side', 'high');
});

add_action('save_post_post', function ($post_id, $post) {
    if (! isset($_POST['capp_newsletter_nonce']) || ! wp_verify_nonce($_POST['capp_newsletter_nonce'], 'capp_newsletter') || ! current_user_can('edit_post', $post_id)) {
        return;
    }
    $modus = in_array($_POST['capp_modus'] ?? 'nein', ['nein', 'entwurf', 'senden'], true) ? $_POST['capp_modus'] : 'nein';
    $tags = array_values(array_filter(array_map('sanitize_title', (array) ($_POST['capp_tags'] ?? []))));
    update_post_meta($post_id, '_capp_newsletter_modus', $modus);
    update_post_meta($post_id, '_capp_newsletter_tags', $tags);
    if ($modus === 'nein' || $post->post_status !== 'publish' || get_post_meta($post_id, '_capp_newsletter_id', true)) {
        return;
    }
    $bild = get_the_post_thumbnail_url($post_id, 'large');
    $r = capp_api('/newsletter', 'POST', [
        'betreff' => get_the_title($post_id),
        'titel' => get_the_title($post_id),
        'text' => wp_strip_all_tags(has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(strip_shortcodes($post->post_content), 90, ' ...'))."\n\nWeiterlesen: ".get_permalink($post_id),
        'vorschautext' => wp_strip_all_tags(get_the_excerpt($post_id)),
        'bild_url' => $bild ?: null,
        'knopf_text' => 'Weiterlesen',
        'knopf_url' => get_permalink($post_id),
        'tags' => $tags,
        'senden' => $modus === 'senden',
        'quelle' => 'wordpress:'.$post_id,
    ]);
    if (is_array($r) && ! empty($r['newsletter_id'])) {
        update_post_meta($post_id, '_capp_newsletter_id', (int) $r['newsletter_id']);
        update_post_meta($post_id, '_capp_newsletter_stand', $modus === 'senden' ? 'gesendet an '.(int) ($r['empfaenger'] ?? 0) : 'Entwurf');
    } else {
        update_post_meta($post_id, '_capp_newsletter_stand', 'Fehler: '.(is_array($r) ? ($r['fehler'] ?? ($r['message'] ?? 'unbekannt')) : 'keine Verbindung'));
    }
}, 10, 2);

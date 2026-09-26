<?php

/**
 * Plugin Name: Lea - Bruecke zur neuen App
 * Description: Shortcode [lea_neueapp_knopf] zeigt angemeldeten Personen einen Knopf "Zur neuen App" mit signiertem Einmal-Link (60 Sekunden). Sichtbar fuer Team und fuer Personen in den Kursen aus LEA_NEUEAPP_KURSE. Angelegt: 25.09.2026
 *
 * Liegt im Repository unter docs/wordpress/, auf dem Server in wp-content/novamira-sandbox/.
 * Das Geheimnis steht in der wp-config.php (LEA_APP_BRIDGE_SECRET), gleich wie
 * tenants.settings.bridge.secret in der App (php84 artisan bridge:secret lea).
 */
if (! defined('ABSPATH')) {
    exit;
}

/* Kurs-IDs, deren Teilnehmerinnen den Knopf sehen (z. B. der Testkurs). Leer: nur Team. */
if (! defined('LEA_NEUEAPP_KURSE')) {
    define('LEA_NEUEAPP_KURSE', '');
}

if (! function_exists('lea_neueapp_token')) {
    function lea_neueapp_token($weiter = '/')
    {
        if (! defined('LEA_APP_BRIDGE_SECRET') || ! is_user_logged_in()) {
            return '';
        }
        $u = wp_get_current_user();
        $daten = rtrim(strtr(base64_encode(wp_json_encode([
            'e' => strtolower($u->user_email), 't' => time() + 60, 'n' => bin2hex(random_bytes(8)), 'w' => $weiter,
        ])), '+/', '-_'), '=');

        return $daten.'.'.hash_hmac('sha256', $daten, LEA_APP_BRIDGE_SECRET);
    }
}

if (! function_exists('lea_neueapp_darf')) {
    function lea_neueapp_darf()
    {
        if (! is_user_logged_in() || ! defined('LEA_APP_BRIDGE_SECRET')) {
            return false;
        }
        if (current_user_can('manage_options') || current_user_can('lea_redaktion')) {
            return true;
        }
        $kurse = array_filter(array_map('intval', explode(',', (string) LEA_NEUEAPP_KURSE)));
        if (! $kurse || ! function_exists('lea_q_my_kurse')) {
            return false;
        }
        $meine = array_map('intval', (array) lea_q_my_kurse(get_current_user_id()));

        return (bool) array_intersect($kurse, $meine);
    }
}

if (! function_exists('lea_neueapp_knopf')) {
    function lea_neueapp_knopf($a = [])
    {
        if (! lea_neueapp_darf()) {
            return '';
        }
        $a = shortcode_atts(['text' => 'Zur neuen App', 'weiter' => '/'], $a);
        $basis = defined('LEA_APP_URL') ? LEA_APP_URL : 'https://app.leawernli.ch';
        $url = $basis.'/sso?token='.rawurlencode(lea_neueapp_token($a['weiter']));

        return '<a class="lea-neueapp-knopf" href="'.esc_url($url).'" rel="nofollow">'.esc_html($a['text']).'</a>';
    }
    add_shortcode('lea_neueapp_knopf', 'lea_neueapp_knopf');
}

<?php

/**
 * Plugin Name: Coaching-App - Anmeldungen aus Elementor-Formularen
 * Description: Traegt Anmeldungen aus den Elementor-Formularen der Website zusaetzlich als Kontakte in die Coaching-App ein (POST /api/anmelden). Laeuft neben der Mailster-Bruecke (lea-mailster-forms.php), bis Mailster abgeschaltet wird. Nichts an den Formularen selbst aendert sich.
 * Version: 1.0
 *
 * Formularname => Tag in der App. 'sofort' = Veranstaltung: ohne Bestaetigungsmail,
 * der Kontakt gilt als bestaetigt (wer sich zu einem Termin anmeldet, will die Zugangsdaten).
 * Newsletter-Formulare gehen mit Double-Opt-in (Bestaetigungsmail aus der App).
 * Kreuzt jemand auf einem Veranstaltungsformular zusaetzlich "Newsletter" an, wird zuerst
 * der Newsletter mit Opt-in angemeldet, dann die Veranstaltung sofort. So bleibt die
 * Newsletter-Einwilligung an den Klick in der Mail gebunden.
 *
 * "Kontakt Lea" fehlt absichtlich: eine Anfrage ist keine Newsletter-Einwilligung.
 */
if (! defined('ABSPATH')) {
    exit;
}

function lea_app_anmeldung_map()
{
    return [
        'Newsletter Footer' => ['tag' => 'newsletter'],
        'Newsletter Alt' => ['tag' => 'newsletter'],
        'Newsletter' => ['tag' => 'newsletter'],
        'Newsletter ADHS-Mütter' => ['tag' => 'newsletter,adhs-muetter'],
        'Webinar Anmeldung' => ['tag' => 'webinar-serie'],
        'CoachClub Warteliste' => ['tag' => 'coachclub-warteliste'],
        'Freebie E-Book' => ['tag' => 'freebie-weniger-sorgen,newsletter'],
        'Live-Abend Hybrid-Coaching' => ['tag' => 'live-abend-hybrid-coaching', 'sofort' => true],
        'Coach-Ausbildung Interessensliste' => ['tag' => 'coach-ausbildung-interessensliste', 'sofort' => true],
    ];
}

function lea_app_anmeldung_url()
{
    $u = function_exists('capp_url') ? capp_url() : rtrim((string) get_option('coaching_app_url', ''), '/');

    return $u;
}

add_action('elementor_pro/forms/new_record', 'lea_app_anmeldung_new_record', 15, 2);
function lea_app_anmeldung_new_record($record, $handler)
{
    $basis = lea_app_anmeldung_url();
    if ($basis === '') {
        return;
    }
    $settings = $record->get('form_settings');
    $name = isset($settings['form_name']) ? trim($settings['form_name']) : '';
    $map = lea_app_anmeldung_map();
    if (! isset($map[$name])) {
        lea_app_anmeldung_log('uebersprungen: Formular "'.$name.'" ist keinem Tag zugeordnet');

        return;
    }
    $conf = wp_parse_args($map[$name], ['tag' => 'newsletter', 'sofort' => false]);

    $fields = $record->get('fields');
    $val = function ($key) use ($fields) {
        return isset($fields[$key]['value']) && is_string($fields[$key]['value']) ? trim($fields[$key]['value']) : '';
    };
    $email = $val('email');
    if (! $email || ! is_email($email)) {
        lea_app_anmeldung_log('Formular "'.$name.'": keine gueltige E-Mail');

        return;
    }
    $vorname = $val('name') ? $val('name') : $val('vorname');
    if ($val('nachname') && strpos($vorname, ' ') === false) {
        $vorname = trim($vorname.' '.$val('nachname'));
    }

    // Newsletter-Haekchen auf einem Veranstaltungsformular: zuerst mit Opt-in anmelden.
    $tags = $conf['tag'];
    if ($conf['sofort'] && $val('newsletter') !== '' && strpos($tags, 'newsletter') === false) {
        lea_app_anmeldung_senden($basis, $name, $email, $vorname, 'newsletter', false);
    }
    lea_app_anmeldung_senden($basis, $name, $email, $vorname, $tags, (bool) $conf['sofort']);
}

function lea_app_anmeldung_senden($basis, $formular, $email, $name, $tag, $sofort)
{
    $r = wp_remote_post($basis.'/api/anmelden', [
        'timeout' => 8,
        'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        'body' => wp_json_encode([
            'email' => $email,
            'name' => $name,
            'tag' => $tag,
            'sofort' => $sofort ? 1 : 0,
            'einwilligung' => 1,
            'herkunft' => 'website:'.sanitize_title($formular),
        ]),
    ]);
    if (is_wp_error($r)) {
        lea_app_anmeldung_log('FEHLER "'.$formular.'" ('.$email.'): '.$r->get_error_message());

        return false;
    }
    $code = (int) wp_remote_retrieve_response_code($r);
    $j = json_decode(wp_remote_retrieve_body($r), true);
    if ($code !== 200 || empty($j['ok'])) {
        lea_app_anmeldung_log('FEHLER "'.$formular.'" ('.$email.'): HTTP '.$code.' '.substr(wp_remote_retrieve_body($r), 0, 200));

        return false;
    }
    lea_app_anmeldung_log('OK "'.$formular.'" -> '.$email.' (Tags '.$tag.', '.($sofort ? 'sofort' : 'Opt-in').', Stand '.($j['stand'] ?? '?').')');

    return true;
}

/** Kleines Protokoll: get_option('lea_app_anmeldung_log'), die letzten 60 Eintraege. */
function lea_app_anmeldung_log($msg)
{
    $log = get_option('lea_app_anmeldung_log', []);
    array_unshift($log, gmdate('Y-m-d H:i:s').' UTC  '.$msg);
    update_option('lea_app_anmeldung_log', array_slice($log, 0, 60), false);
}

/* ---------- Nach Mailster: alte Abmelde- und Profil-Links aus frueheren Mails landen in der App ---------- */
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }
    $basis = lea_app_anmeldung_url();
    if ($basis === '') {
        return;
    }
    $pfad = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    $alt = preg_match('~^newsletter-anmeldung/(abmelden|profil)(/|$)~', $pfad) || isset($_GET['mailster_unsubscribe']) || isset($_GET['mailster_profile']);
    if ($alt) {
        wp_redirect($basis.'/n/abmelden', 302);
        exit;
    }
}, 0);

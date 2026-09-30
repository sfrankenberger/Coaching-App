<?php

/**
 * Plugin Name: Lea - Systemmails ueber Mailgun (ohne Mailster)
 * Description: Schickt alle wp_mail()-Mails (Anmeldelinks, Abendmail, Erinnerungen, Formulare) ueber die Mailgun-API, so wie es vorher Mailster tat. Zugang in der Option lea_mailgun (apikey, domain, endpoint eu|us, from, from_name), einmal serverseitig aus den Mailster-Einstellungen uebernommen. Angelegt: 30.09.2026
 */
if (! defined('ABSPATH')) {
    exit;
}

function lea_mg_conf()
{
    $c = get_option('lea_mailgun');

    return is_array($c) && ! empty($c['apikey']) && ! empty($c['domain']) ? $c : null;
}

add_filter('pre_wp_mail', 'lea_mg_pre_wp_mail', 10, 2);
function lea_mg_pre_wp_mail($null, $atts)
{
    $c = lea_mg_conf();
    if (! $c) {
        return null;   // kein Zugang: WordPress schickt selbst
    }
    $to = $atts['to'] ?? [];
    $to = is_array($to) ? $to : explode(',', (string) $to);
    $to = array_values(array_filter(array_map('trim', $to)));
    if (! $to) {
        return false;
    }
    $subject = (string) ($atts['subject'] ?? '');
    $message = (string) ($atts['message'] ?? '');
    $headers = $atts['headers'] ?? [];
    $headers = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers));
    $from = $c['from'];
    $from_name = $c['from_name'];
    $ist_html = stripos((string) apply_filters('wp_mail_content_type', 'text/plain'), 'text/html') !== false;   // Vorgabe per Filter, Kopfzeile geht vor
    $reply = '';
    $cc = [];
    $bcc = [];
    foreach ($headers as $h) {
        if (strpos($h, ':') === false) {
            continue;
        }
        [$name, $wert] = array_map('trim', explode(':', $h, 2));
        switch (strtolower($name)) {
            case 'content-type':
                $ist_html = stripos($wert, 'text/html') !== false;
                break;
            case 'from':
                if (preg_match('~^(.*)<([^>]+)>~', $wert, $m)) {
                    $from = trim($m[2]);
                    $from_name = trim($m[1], " \"'") ?: $from_name;
                } elseif (is_email($wert)) {
                    $from = $wert;
                }
                break;
            case 'reply-to':
                $reply = $wert;
                break;
            case 'cc':
                $cc = array_merge($cc, array_map('trim', explode(',', $wert)));
                break;
            case 'bcc':
                $bcc = array_merge($bcc, array_map('trim', explode(',', $wert)));
                break;
        }
    }
    // Absender immer die eigene Domain, sonst lehnt Mailgun ab; fremde Absender werden Antwortadresse.
    if (substr(strrchr($from, '@'), 1) !== $c['domain'] && substr(strrchr($from, '@'), 1) !== preg_replace('~^mail\.~', '', $c['domain'])) {
        $reply = $reply ?: $from;
        $from = $c['from'];
    }
    $from = apply_filters('wp_mail_from', $from);
    $from_name = apply_filters('wp_mail_from_name', $from_name);

    $body = ['from' => sprintf('%s <%s>', $from_name, $from), 'to' => implode(',', $to), 'subject' => $subject];
    if ($ist_html) {
        $body['html'] = $message;
        $body['text'] = trim(html_entity_decode(wp_strip_all_tags(preg_replace('~<br\s*/?>|</p>~i', "\n", $message)), ENT_QUOTES, 'UTF-8'));
    } else {
        $body['text'] = $message;
    }
    if ($reply) {
        $body['h:Reply-To'] = $reply;
    }
    if ($cc) {
        $body['cc'] = implode(',', $cc);
    }
    if ($bcc) {
        $body['bcc'] = implode(',', $bcc);
    }

    $boundary = '----lea'.wp_generate_password(24, false);
    $payload = '';
    foreach ($body as $k => $v) {
        $payload .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
    }
    foreach ((array) ($atts['attachments'] ?? []) as $pfad) {
        if (! is_string($pfad) || ! is_readable($pfad)) {
            continue;
        }
        $payload .= "--$boundary\r\nContent-Disposition: form-data; name=\"attachment\"; filename=\"".basename($pfad)."\"\r\nContent-Type: application/octet-stream\r\n\r\n".file_get_contents($pfad)."\r\n";
    }
    $payload .= "--$boundary--\r\n";

    $basis = ($c['endpoint'] ?? 'eu') === 'eu' ? 'https://api.eu.mailgun.net/v3/' : 'https://api.mailgun.net/v3/';
    $r = wp_remote_post($basis.rawurlencode($c['domain']).'/messages', [
        'timeout' => 15,
        'headers' => ['Authorization' => 'Basic '.base64_encode('api:'.$c['apikey']), 'Content-Type' => 'multipart/form-data; boundary='.$boundary],
        'body' => $payload,
    ]);
    $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
    if ($code !== 200) {
        $fehler = is_wp_error($r) ? $r->get_error_message() : 'HTTP '.$code.' '.substr((string) wp_remote_retrieve_body($r), 0, 200);
        lea_mg_log('FEHLER an '.implode(',', $to).' "'.$subject.'": '.$fehler);
        do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $fehler, $atts));

        return false;
    }
    lea_mg_log('OK an '.implode(',', $to).' "'.mb_substr($subject, 0, 60).'"');

    return true;
}

/** Protokoll: get_option('lea_mailgun_log'), die letzten 60 Eintraege. */
function lea_mg_log($msg)
{
    $log = get_option('lea_mailgun_log', []);
    array_unshift($log, gmdate('Y-m-d H:i:s').' UTC  '.$msg);
    update_option('lea_mailgun_log', array_slice($log, 0, 60), false);
}

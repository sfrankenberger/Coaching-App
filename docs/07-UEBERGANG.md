# 07 Übergang: Parallelbetrieb mit leawernli.ch

Grundregel: neben dem Bestehenden bauen, umschalten, wenn das Neue läuft. leawernli.ch bleibt live, bis hier alles trägt. In WordPress wird nichts abgebaut, nur zwei kleine Dinge ergänzt: der Knopf "Zur neuen App" (SSO-Brücke) und der Woo-Webhook.

## 1. Zugänge aus dem Shop (WooCommerce-Webhook)

In der App: Angebote unter `/coach/offers` anlegen, je Angebot die Woo-Produkt-IDs eintragen (Produkt oder Variante). Der Import legt Angebote für die Kurse (`kurs-<ID>`) und den Club (`club`, Produkt 1524) bereits an. Angebote vom Typ "Club (Abo)" reagieren nur auf Abo-Ereignisse, alle anderen auf Bestellungen.

Geheimnis unter `/plattform` beim Mandanten setzen: `settings.shop.webhook_secret` (langer Zufallswert).

In WooCommerce (Einstellungen > Erweitert > Webhooks) zwei Webhooks anlegen:

| Name | Thema | Ziel-URL | Geheimnis |
|---|---|---|---|
| App Bestellungen | Bestellung aktualisiert | `https://app.leawernli.ch/hooks/woocommerce` | wie `shop.webhook_secret` |
| App Abos | Abonnement aktualisiert (WC Subscriptions) | dieselbe | dasselbe |

Was passiert: Bestellung `completed` oder `processing` gibt Zugang (Laufzeit aus dem Angebot, z. B. 365 Tage). `refunded`, `cancelled`, `failed` beenden ihn. Abo `active` gibt Zugang ohne Ende, `pending-cancel` bis zum Enddatum, `on-hold`, `cancelled`, `expired` beenden ihn. Neue Mailadressen werden als Person mit Mitgliedschaft angelegt und bekommen eine Willkommensmail mit Anmeldelink (7 Tage gültig). Jede Lieferung steht in `webhook_logs`.

Prüfen: in Woo beim Webhook "Lieferungen" anschauen (Antwort `{"ok":true,"status":"ok",...}`), in der App unter `/coach/offers` beim Angebot die Zugänge.

## 2. Brücke aus dem alten Mitgliederbereich (SSO)

Die App prüft einen signierten Link: `https://app.leawernli.ch/sso?token=<daten>.<signatur>`. Daten sind base64url(JSON `{e: mail, t: ablauf (Unix), n: nonce, w: weiter}`), Signatur ist `hash_hmac('sha256', daten, geheimnis)` als Hex. 60 Sekunden gültig, einmal einlösbar, nur für Personen mit Mitgliedschaft.

Geheimnis erzeugen: `php84 artisan bridge:secret lea` und in die `wp-config.php` von leawernli.ch eintragen:

```php
define('LEA_APP_BRIDGE_SECRET', '...');
define('LEA_APP_URL', 'https://app.leawernli.ch');
```

Snippet für `wp-content/novamira-sandbox/lea-app-bruecke.php` (als Plugin oder per `include`): Shortcode `[lea_app_knopf]` zeigt angemeldeten Personen den Knopf "Zur neuen App". Wer ihn sieht, steuert die Option `lea_app_kurse` (Kurs-IDs, kommagetrennt): nur Personen mit Zugang zu einem dieser Kurse. Leer heisst: alle.

```php
<?php
/**
 * Plugin Name: Lea - Bruecke zur neuen App
 * Description: Knopf "Zur neuen App" mit signiertem Einmal-Link. Angelegt: 25.09.2026
 */
if (!defined('ABSPATH')) exit;

function lea_app_token($weiter = '/') {
    if (!defined('LEA_APP_BRIDGE_SECRET') || !is_user_logged_in()) return '';
    $u = wp_get_current_user();
    $daten = rtrim(strtr(base64_encode(wp_json_encode(array(
        'e' => strtolower($u->user_email), 't' => time() + 60, 'n' => bin2hex(random_bytes(8)), 'w' => $weiter,
    ))), '+/', '-_'), '=');
    return $daten . '.' . hash_hmac('sha256', $daten, LEA_APP_BRIDGE_SECRET);
}

function lea_app_darf() {
    $kurse = array_filter(array_map('intval', explode(',', (string) get_option('lea_app_kurse', ''))));
    if (!$kurse) return is_user_logged_in();
    if (!function_exists('lea_zugang_darf')) return current_user_can('manage_options');
    foreach ($kurse as $k) { if (lea_zugang_darf($k)) return true; }
    return current_user_can('manage_options');
}

add_shortcode('lea_app_knopf', function ($a) {
    if (!lea_app_darf()) return '';
    $a = shortcode_atts(array('text' => 'Zur neuen App', 'weiter' => '/'), $a);
    $url = (defined('LEA_APP_URL') ? LEA_APP_URL : 'https://app.leawernli.ch') . '/sso?token=' . rawurlencode(lea_app_token($a['weiter']));
    return '<a class="lea-app-knopf" href="' . esc_url($url) . '">' . esc_html($a['text']) . '</a>';
});
```

Der Link wird beim Seitenaufbau erzeugt und gilt 60 Sekunden. Wer länger wartet, landet auf `/anmelden` mit dem Hinweis, sich dort anzumelden (Magic Link), das ist der Normalweg. Testen: `[lea_app_knopf weiter="/kurse"]` auf der Testkurs-Seite, Option `lea_app_kurse` = ID des Testkurses.

## 3. Was während des Parallelbetriebs wo gepflegt wird

| Was | Wo | Warum |
|---|---|---|
| Personen, Zugänge | WordPress (Woo) | Webhook trägt in die App nach; von Hand Angelegtes in der App bleibt |
| Kurse, Lektionen, Material, Termine des Testkurses | App (Coach-Bereich) | Der Testkurs läuft in der App; Import nicht mehr für diesen Kurs laufen lassen, sonst überschreibt er Änderungen an Titeln und Texten |
| Andere Kurse | WordPress | Import wiederholen, bis umgeschaltet wird |
| Impulse, Podcast | WordPress | Feeds holen stündlich; Themen und Kurztexte kommen aus dem Import oder per KI |
| Chats, Aufgaben, Reflexionen der Testkurs-Teilnehmerinnen | App | Nicht zurück nach WordPress |

Der Import ist wiederholbar (legacy_id) und überschreibt Inhaltsfelder aus WordPress, nicht aber, was Personen in der App selbst gemacht haben (Antworten, Fortschritt, Merkliste, Lesestand). Für den Testkurs gilt: Import 2 und 3 nur noch mit Bedacht.

## 4. Umschalten (nach Leas Freigabe)

1. Schreibstopp im alten Mitgliederbereich (Hinweis an alle, Knopf "Zur neuen App" für alle sichtbar: Option `lea_app_kurse` leeren)
2. Letzter Import: `import:wordpress lea --only=alles`
3. `/mitgliederbereich/*` auf leawernli.ch per 301 auf `https://app.leawernli.ch` umleiten
4. Sandbox-Module im WordPress abschalten (nicht löschen), nach 30 Tagen aufräumen
5. Woo-Webhook bleibt, Feeds bleiben, Brücke bleibt (für den Weg von der Website in die App)

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

Das Snippet liegt im Repository unter `docs/wordpress/lea-neueapp-bruecke.php` und auf dem Server als `wp-content/novamira-sandbox/lea-neueapp-bruecke.php` (eingerichtet am 25.09.2026). Novamira lädt jede Datei im Sandbox-Ordner automatisch, ein Fehler schaltet alle Sandbox-Dateien in den abgesicherten Modus: darum eigener Präfix `lea_neueapp_` und `function_exists`-Wächter, und vor jeder Änderung `php -l`.

Shortcode `[lea_neueapp_knopf]` (Optionen `text`, `weiter`). Sichtbar für Lea und das Team, dazu für Personen in den Kursen aus der Konstante `LEA_NEUEAPP_KURSE` im Snippet (kommagetrennte Kurs-IDs, anfangs leer). Für den Testkurs dort die Kurs-ID eintragen und den Shortcode auf der Kursseite oder im Dashboard des alten Mitgliederbereichs platzieren.

Der Link wird beim Seitenaufbau erzeugt und gilt 60 Sekunden. Wer länger wartet, landet auf `/anmelden` mit dem Hinweis, sich dort anzumelden (Magic Link), das ist der Normalweg. Testen: als Lea angemeldet `[lea_neueapp_knopf weiter="/kurse"]` auf einer Seite in der Vorschau.

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

1. Schreibstopp im alten Mitgliederbereich (Hinweis an alle, Knopf "Zur neuen App" für alle: in `lea_neueapp_darf()` den Kursfilter entfernen)
2. Letzter Import: `import:wordpress lea --only=alles`
3. `/mitgliederbereich/*` auf leawernli.ch per 301 auf `https://app.leawernli.ch` umleiten
4. Sandbox-Module im WordPress abschalten (nicht löschen), nach 30 Tagen aufräumen
5. Woo-Webhook bleibt, Feeds bleiben, Brücke bleibt (für den Weg von der Website in die App)

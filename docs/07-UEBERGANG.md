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

## 2b. Testbetrieb

Solange beide Systeme laufen, ist in der App der Testbetrieb an (Coach-Bereich, Einstellungen): Erinnerungen, Abendmail, Push, Rundnachrichten und die automatische Willkommensmail aus dem Shop gehen nur an die dort freigegebenen Adressen. Anmeldelinks und Einladungen, die Lea selbst auslöst, gehen an alle. Für den Testkurs die Adressen seiner Teilnehmerinnen freigeben. Beim Umschalten den Testbetrieb ausschalten.

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
3. `/mitgliederbereich/*` auf leawernli.ch per 301 auf `https://app.leawernli.ch/mitgliederbereich/...` umleiten,
   Pfad und Query mitgeben (siehe 4a): die App ordnet alte Adressen selbst zu
4. Sandbox-Module im WordPress abschalten (nicht löschen), nach 30 Tagen aufräumen
5. Woo-Webhook bleibt, Feeds bleiben, Brücke bleibt (für den Weg von der Website in die App)
6. Mailster ist seit 1.10. (nachts) deaktiviert, Teilnehmerinnen merken nichts: Systemmails der Website (Anmeldelinks,
   Abendmail, Erinnerungen, Chat) gehen ueber `lea-wp-mail-mailgun.php` weiter per Mailgun (Zugang in der Option
   `lea_mailgun`, aus den Mailster-Einstellungen uebernommen, Protokoll `lea_mailgun_log`). Die drei Autoresponder mit
   Wirkung sind Serien in der App (Willkommen auf `newsletter`, E-Book-Strecke 0/7/14 Tage auf `freebie-weniger-sorgen`,
   Aufzeichnung auf `live-abend-hybrid-coaching`); die Autoresponder "Beitrag veroeffentlicht" ersetzt die Newsletter-Box
   im Beitragseditor. Alte Abmelde- und Profil-Links aus Mailster-Mails leiten auf `app.leawernli.ch/n/abmelden`
   (Adresse eingeben, Link kommt per Mail). Formulare der Website schreiben ueber `lea-app-anmeldung.php` in die App.
   Am Umzugstag nur noch: Kontakte-Import wiederholen (`--only=kontakte`, holt Anmeldungen vom 30.09. bis 1.10. nach,
   falls welche nur in Mailster gelandet sind), Mailster-Plugins loeschen (nach 30 Tagen).
   Gelernt beim Abschalten: `lea-mailster-optik.php` rief `mailster_option()` ohne Waechter im Frontend, die Website
   war zwei Minuten auf 500; die Datei liegt jetzt unter `novamira-sandbox/alt/`. Der Gratiskurs "Der Anfang"
   (`lea-anfang-anmeldung.php`) schreibt seither in die App (Tags `freebie-der-anfang`, `coach-ausbildung-interessensliste`,
   Newsletter mit Opt-in) und nimmt Absender und Briefpapier aus `lea_mailgun` bzw. den alten Mailster-Optionen.
7. WooCommerce bleibt bis zum Umzugstag, weil der alte Mitgliederbereich daran haengt (Anmeldung ueber "Mein Account",
   27 Sandbox-Module rufen Woo-Funktionen, Verkaufsseiten der Website verlinken `/produkt/...`, 7 Bestellungen fuer
   bexio). Reihenfolge am Umzugstag: Preise und Sichtbarkeit der Angebote in der App setzen (Woo-Produkte 1518, 1623,
   1722, 1879, 2430, 2432, 2434), Kaufknoepfe der Website auf `[app_kaufen slug="..."]`, 301 von `/produkt/{slug}` auf
   `/kaufen/{slug}` und von `/shop/`, `/warenkorb/`, `/kasse/`, `/mein-account/` auf die App, dann Woo-Plugins
   deaktivieren (woocommerce, -subscriptions, -gateway-stripe, -order-status-control, woo-update-manager,
   email-customizer-for-woocommerce). Abo-Umzug (4b, Punkt 3) entfaellt: es gibt kein laufendes Abo.

## 5. Installierte App (Home-Bildschirm) und Push beim Umzug

Die alte App ist eine PWA auf `leawernli.ch` (Manifest-Scope `/`, Start `/mitgliederbereich/`, Service Worker
`/lea-sw.js`). Die neue laeuft auf `app.leawernli.ch`. Zwei Dinge haengen an der Domain:

**Das Icon auf dem Home-Bildschirm.** Es oeffnet weiter `leawernli.ch/mitgliederbereich/`. Mit der Weiterleitung
(Punkt 4.3) landet die Person zwar in der neuen App, auf dem iPhone aber in einem Browserfenster innerhalb der alten
App (fremde Domain, ausserhalb des Scopes), mit Leiste oben und ohne eigenes Icon. Darum: einmal neu auf den
Home-Bildschirm legen. Die neue App zeigt dafuer beim ersten Besuch das Installations-Fenster (`app-sheet`). Der
alte Eintrag darf danach geloescht werden. Es gibt keinen Weg, ein installiertes Icon auf eine andere Domain
umzuhaengen, ausser die neue App wuerde unter derselben Domain laufen (Reverse Proxy auf `leawernli.ch/app/`),
was fuer die Laravel-App viel Umbau waere und hier nicht geplant ist.

**Push-Abos.** Sie sind an die VAPID-Schluessel und den Service Worker der alten Domain gebunden, nicht an die
URL, die beim Antippen aufgeht. Darum uebernimmt die App beide:

```bash
php84 artisan import:wordpress lea --only=push --dry-run -v     # zeigt, wer Abos hat
php84 artisan import:wordpress lea --only=push --schluessel      # Abos und VAPID-Schluessel uebernehmen
```

Danach schickt die App ihre Push-Nachrichten an die alten Abos, der alte Service Worker zeigt sie an
(gleiches Nachrichtenformat: `title`, `body`, `url`, `tag`, `icon`) und oeffnet beim Antippen die URL aus der
Nachricht, also die App. Niemand muss Push neu einschalten, solange `leawernli.ch` erreichbar bleibt und der
alte Service Worker nicht abgemeldet wird (Sandbox-Modul `lea-app.php` also nicht abschalten, nur die Seiten
weiterleiten). Wer die neue App auf den Home-Bildschirm legt, bekommt dort beim naechsten Besuch die Push-Frage
und ist dann doppelt eingetragen; das ist harmlos, der Browser bindet je Abo einen Service Worker.
Achtung: `--schluessel` ersetzt die in der App erzeugten Schluessel, Abos aus dem Testbetrieb der App muessen
dann einmal neu eingeschaltet werden (Profil). Darum die Uebernahme genau einmal machen, beim Umschalten.

## 4a. Alte Adressen (Altlinks)

Alte Mails, Kalendereintraege und Push-Nachrichten zeigen auf `leawernli.ch/mitgliederbereich/...`. Damit die Personen
nicht nur auf der Startseite landen, nimmt die App diese Pfade unter `/mitgliederbereich/{pfad}` entgegen
(`AltlinkController`, `App\Support\Altlinks`) und leitet weiter:

- Bereichsnamen: `termine`, `kalender`, `gespraech`, `chat`, `nachrichten`, `aufgaben`, `notizen`, `journal`,
  `reflexion`, `wochencheck`, `material`, `ressourcen`, `downloads`, `impulse`, `neuigkeiten`, `podcast`, `profil`,
  `mitgliedschaft`, `community`, `fragen`, `buchen`, `hilfe`, `nachschlagen`, `merkliste`, `projekte` (volle Liste
  in `Altlinks::BEREICHE`). Unbekanntes landet auf der Startseite.
- Kurs: `kurs/{slug oder WordPress-ID}`, dazu `/austausch`, `/fragen`, `/woche/{n}`; Lektion: `lektion/{WordPress-ID}`
  oder `?kurs=...&lektion=...` (ueber `units.legacy_id`); Termin: `termine/{WordPress-ID}`; Beitrag: `impulse/{slug}`
  oder `?p={WordPress-ID}`.
- Eigene Zuordnung je Mandant in `tenants.settings.altlinks` (`{"alter/pfad": "/neuer/pfad"}`), laengster Treffer
  gewinnt, geht vor der eingebauten Tabelle. So lassen sich beim Umschalten die wirklich verschickten Adressen
  nachtragen, ohne Code.
- Nicht angemeldete Personen gehen ueber `/anmelden?weiter=...` und landen nach dem Link am Ziel.

In WordPress darum nicht pauschal auf die Startseite leiten, sondern Pfad und Query mitgeben:
`RedirectMatch 301 ^/mitgliederbereich/(.*)$ https://app.leawernli.ch/mitgliederbereich/$1` (Query haengt Apache
von selbst an). Vor dem Umschalten die Liste der tatsaechlich verwendeten Adressen aus den Mailvorlagen und
`lea-push.php` ziehen und gegen `Altlinks` pruefen (`php84 artisan tinker`, `(new App\Support\Altlinks($tenant))->ziel('...')`).

## 4b. Verkauf umziehen: WooCommerce abschalten, Abo nach Stripe

Stand 29.09.: kein Kauf in WooCommerce, ein laufendes Abo (Stripe als Zahlungsdienst hinter WooCommerce Subscriptions).

1. Angebote in der App pruefen: Preise, Sichtbarkeit, `abo_intervall` beim Club-Angebot, Kauflinks (`/kaufen/{slug}`).
2. Kaufknoepfe auf der Website auf die App umstellen: `[app_kaufen slug="..."]` bzw. Links auf `/kaufen/{slug}` aus
   `resources/wordpress/app-angebote.php`. Danke-Seite und Mails in WordPress nicht mehr noetig, die App hat eigene.
3. Das laufende Abo umziehen. Aus WordPress die Stripe-Kundennummer holen (Bestellung oder Abo, Meta `_stripe_customer_id`,
   beginnt mit `cus_`) und den naechsten Abbuchungstag ablesen. Dann:
   ```bash
   php84 artisan abo:umziehen lea kundin@mail.ch club-abo --kunde=cus_xxx --ab=2026-11-01 --trocken
   php84 artisan abo:umziehen lea kundin@mail.ch club-abo --kunde=cus_xxx --ab=2026-11-01
   ```
   Die App legt in Stripe ein Abo auf die bestehende Kundin an (gespeicherte Zahlungsmethode bleibt, erste Abbuchung
   am genannten Tag, bis dahin ohne Berechnung) und gibt den Zugang sofort. Danach das Abo in WooCommerce Subscriptions
   auf "abgebrochen" setzen (nicht "storniert", sonst schickt Woo eine Mail) und das Woo-Webhook-Ereignis pruefen.
4. WooCommerce-Webhook in der App loeschen (`shop.webhook_secret` leeren), in WordPress WooCommerce, German Market,
   Kasse, Preise und das bexio-Modul deaktivieren. Erst deaktivieren, nach 30 Tagen loeschen.
5. Stripe-Dashboard: Webhook auf `https://app.leawernli.ch/hooks/stripe` mit den Ereignissen `checkout.session.completed`,
   `checkout.session.async_payment_succeeded`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`,
   `customer.subscription.deleted`; Kundenportal unter Einstellungen, Billing, Customer portal einschalten (Kuendigen erlauben,
   Zahlungsmethode aendern, Rechnungsverlauf).

**Anmeldung ohne Magic Link.** Die Weiterleitung von `/mitgliederbereich/*` sollte fuer angemeldete Personen
ueber die Bruecke gehen (`lea_neueapp_token()` aus `lea-neueapp-bruecke.php`, signierter Einmal-Link), dann
sind sie in der App sofort angemeldet. Nicht angemeldete landen auf `/anmelden`.

## 6. Klarheitsgespraech fuer Gaeste in der App

`https://app.leawernli.ch/buchen/gast/erst` (Buchungsart `erst`, nur offene Arten) ist das Formular ohne Anmeldung:
Name, Mail, Handynummer, Zeit aus den Kalenderbloecken, Vorbereitungsfragen, Honigtopf gegen Bots. Die Person bekommt
ein Konto als Gast (Rolle `guest`, sieht Gratis-Inhalte, Impulse, Podcast und ihren Termin), die Buchung landet wie
jede andere im Google-Kalender und beim Team, und sie bekommt eine Mail (`GastBuchungMail`) mit Bestaetigung, Zoom-Link
und Anmeldelink in die App (sieben Tage). Die Website verlinkt den Knopf auf `/klarheitsgespraech/` dorthin, dann
kann das Website-Modul `novamira-coaching` abgeschaltet werden. Beide lesen bis dahin dieselben Bloecke
("Coachingblock" und "Coaching Block" gelten beide) und schreiben in denselben Kalender, Doppelbuchungen gibt es nicht.


# G5 Lueckenliste: Mails, Benachrichtigungen, Shop, Rechnung, Zugang, Anmeldung, Website-Technik

Stand der neuen App: Commit f337175 (29.09.2026). Alle Aussagen am Code geprueft (grep und Lesen). Wo `docs/abgleich/inhalte-shop.md` und `kommunikation.md` etwas anderes sagen, ist das dort veraltet: bexio-Anbindung, Verkaufen im Dossier, Meine Buchungen, Kauf auf Rechnung (on-hold), Neuer-Termin-Meldung und Kasse `/kaufen` gibt es inzwischen.

## Gelesene Dateien (alt/novamira-sandbox)

Mail und Newsletter
- lea-mails.php: Mailrahmen, Passwortmail
- lea-mail-bild.php: Beitragsbild fuer Newsletter
- lea-mail-debug-log.php: temporaeres Mail-Protokoll
- lea-mail-template.php: Mailster-Vorlage, Module, Button
- lea-abendmail.php: Sammelmail am Abend, Hinweiskarte
- lea-mailstrecke.php: Mailstrecken-Beitraege nie an Liste
- lea-mailster-editor-toolbar.php: Editor-Knoepfe fuer Mailster
- lea-mailster-forms.php: Formulare tragen in Mailster-Listen ein
- lea-mailster-optik.php: Mailster-Mails und Formulare in Leas Optik
- lea-mailster-texte.php: Mailster-Texte auf Du
- lea-newsletter-doi.php: eigenes Double-Opt-in per Link
- newsletter-testversand.php: Test-Newsletter aus dem Entwurf
- lea-testversand-bruecke.php: Testversand gleich wie echter Versand
- lea-club-mailster.php (Nachbar, fuer Willkommensmail und Kampagnen mitgelesen)

Push, Telegram, Handy
- lea-push.php: Web Push, Beitrag-Push, Test-Push
- lea-telegram-anschluss.php: Push nach Telegram, Antworten zurueck
- lea-pull.php: Ziehen zum Aktualisieren

Shop, Rechnung, Zugang
- lea-kasse.php: Zahlart Rechnung, Zustimmungen, Verkaufskern
- lea-verkauf.php: Person anlegen, Verkaufen im Dossier
- lea-produkt-verkauf.php: Kaufknopf-Text, kein Versand-Hinweis
- lea-preis.php: Preis nach Land, Direktkauf
- lea-preisformat.php: Zahlformat je Waehrung
- lea-waehrung.php: CHF/EUR, feste Waehrung je Kundin
- lea-rechnung.php: Rechnungskopf, Kundennummer (German Market)
- .lea-rechnung.php.disabled: leer, stillgelegt
- lea-bexio.php: Rechnungen, Zahlung, Abgleich, Einstellungen
- lea-bexio-kunden.php: Rechnungen je Person, stille Konten
- lea-zahlen.php: Umsatz und offene Posten
- lea-shop-zugang.php: Produkt schaltet Kurse frei
- lea-zugang.php: Kursinhalte nur mit Zugang
- lea-zugang-direkt.php: Zugaenge im Profil, Ablauf, Abo
- lea-zugang-sichern.php: Passwort, Passkey, Google, Apple anbieten
- lea-mitgliedschaft-kuendigung.php: Kuendbar je Plan
- lea-mitgliedschaft-neu.php: Meine Buchungen im Profil
- lea-woo-checkout-design.php: Kasse und Danke-Seite Optik
- lea-woo-texte.php: Woo-Texte auf Du und Schweizer Schreibweise
- lea-woo-thankyou.php: warme Danke-Seite
- lea-webinar-danke.php: Danke-Seite Live-Abend
- lea-freebie-redirects.php: 301 fuer umbenannte Seiten
- lea-einstieg-cta.php: Einstieg unter jedem Beitrag
- lea-adhs-muetter.php: Einstieg fuer ADHS-Beitraege

Anmeldung
- lea-anmeldedienste.php: Google und Apple, Zugangsdaten
- lea-apple-login.php: Mit Apple anmelden
- lea-login-name.php: Anmeldename gleich Mailadresse
- lea-login-optionen.php: Passkey-Texte, Login-Optik
- lea-demo-login.php: Einmal-Link fuers Demokonto
- lea-gast.php: Gastzugang mit kleinem Menue
- lea-member-guard.php: Bereich nur angemeldet
- lea-doppelschutz.php: Doppelklick-Schutz beim Senden
- lea-zugriffsschutz.php: REST und Direktadressen sperren
- lea-neueapp-bruecke.php: Knopf "Zur neuen App"
- lea-umzug.php: 301 fuer umgezogene Seiten

Website-Technik
- lea-design.php, lea-css-buendel.php: CSS-Optionen, Stile buendeln
- lea-blog-filter-css.php, lea-blog-frei.php, lea-blog-styles.php: Blogseite
- lea-brand-override.php, lea-fontawesome.php, lea-elementor-icons.php: Website-Optik
- lea-externe-bilder.php, lea-svg-uploads.php: WordPress-Technik
- lea-admin-farbschema.php, lea-adminleiste-aufraeumen.php: WP-Backend
- lea-page-categories.php, lea-beitraege-umbenennen.php: WP-Backend
- lea-altlinks-301.php: 301 fuer alte URLs
- .lea-json-reparatur.php.disabled, .lea-convert-tool.php.disabled: leer, stillgelegt
- lea-mobile-slider.php: Slider Startseite mobil
- lea-ui.php: Termin-Badges, Filter, Monatstrenner, Mobil-Feinschliff
- lea-button.php, lea-button-mce.js: Button-Shortcode fuer Website und Mails
- .htaccess, web.config, index.html: Sandbox-Ordner sperren

JS aus WordPress-Optionen: in meinen Dateien wird kein `lea_xx_js` gelesen. Die einzige Option mit Code ist `lea_design_css` (reines CSS, lea-design.php). Alles andere ist PHP mit Inline-Skript.

## Tabelle

Status: vorhanden / teilweise / fehlt / Website / Altlast.

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| **MAILS** | | | | |
| Einheitlicher Mailrahmen mit Logo, Titel, Fusszeile (Website, Kontakt, Jahr) | lea-mails.php | `resources/views/mail/*.blade.php` (Branding-Farben, Schrift, Radius) | teilweise | Logo aus Branding (`logo_url`) wird in Mails nicht gezeigt, nur der App-Name als Text. Keine Fusszeile mit Website, Kontaktadresse, Copyright. Jede Mailvorlage hat den Rahmen einzeln kopiert (kein gemeinsames Layout). |
| Passwort-zuruecksetzen-Mail im Rahmen, Link gilt einen Tag | lea-mails.php | `MagicLinkMail`, Login per Link (15 Min) | vorhanden (anders) | Kein "Passwort vergessen" noetig, Magic Link ersetzt es. Passwort ist freiwillig (Profil). |
| Keine zweite WordPress-Willkommensmail beim Anlegen | lea-mails.php | - | Altlast | Nur WordPress. |
| Mails als HTML erzwingen | lea-mails.php | Laravel | Altlast | |
| Mail-Debug-Log (Versuch, Erfolg, Fehler, letzte 80) | lea-mail-debug-log.php | Laravel-Log, failed_jobs | Altlast | War laut Datei "temporaer". Keine Ansicht "Was wurde wann verschickt" fuer Lea oder das Team (nicht geprueft ob eine Mail-Historie noetig ist). |
| Absender, Antwortadresse je Mandant | (Mailster-Optionen) | `settings.mail`, Coach-Einstellungen "Absender der Mails" | vorhanden | |
| **ABENDMAIL** | | | | |
| Abendmail 19:30 nur an Personen ohne Push, nur bei Neuem, hoechstens einmal am Tag, nie an Lea/Team | lea-abendmail.php | `Runden::abendmail`, Scheduler 19:30, `memberships.digest_sent_at` | vorhanden | Zusaetzlich sind Telegram-Nutzerinnen ausgenommen (alt nur Push). Testbetrieb-Schutz neu. |
| Abendmail-Inhalt: Karten mit Text und je Eintrag ein Link "Ansehen und antworten", maximal 6 | lea-abendmail.php (`lea_am_text`) | `Runden::neuesFuer`, `mail/nachricht.blade.php` | teilweise | Neu eine Textliste mit "•" und ein einziger Knopf "Ansehen" auf die Startseite, bis 30 Eintraege. Kein Link je Eintrag. Dafuer mehr Quellen (Beitraege, Podcast, Aufzeichnungen, Termine, Material, Aufgaben). |
| Abendmail abschaltbar in der App | lea-abendmail.php (`lea_am_aus`) | Profil "Was dich erreicht" (`ProfilController::notifications`) | vorhanden | |
| Hinweiskarte im Alltag "Willst du es merken, wenn Lea schreibt?" mit "Zeig mir wie" und dauerhaft wegklickbar | lea-abendmail.php (`lea_am_hinweis`) | `#app-sheet` in `layouts/app.blade.php`, `public/js/app.js` (Installieren und Push) | teilweise | Fenster erscheint nur auf der Startseite, "Jetzt nicht" gilt nur bis morgen (localStorage), kein dauerhaftes Ausblenden, kein Text, der zwischen "du bekommst eine Abendmail" und "du bist bereits auf Push" unterscheidet. |
| Protokoll der letzten Abendrunde (Zeit, geprueft, gesendet) | lea-abendmail.php (`lea_am_letzte_runde`) | Konsolenausgabe von `benachrichtigungen:runde` | teilweise | Nicht dauerhaft gespeichert, nur klein. |
| **PUSH, TELEGRAM, HANDY** | | | | |
| Push einschalten je Geraet, Abo speichern und entfernen | lea-push.php | `PushController`, Profil, `push_subscriptions`, `public/js/app.js` | vorhanden | |
| Push senden, abgelaufene Abos automatisch entfernen | lea-push.php (`lea_push_senden`) | `WebPushChannel` | vorhanden | |
| VAPID-Schluessel je Mandant | lea-push.php | `settings.push.vapid`, `php artisan push:keys`, `PushKeys` | vorhanden | Uebernahme der alten Schluessel und Abos: `import:wordpress --only=push --schluessel` (docs/07). |
| Neuer Beitrag geht automatisch als Push an alle, die ihn sehen duerfen (Free an alle mit Push, Club-intern an alle mit Kurs, Kurs an dessen Teilnehmerinnen) | lea-push.php | `PostObserver` | teilweise | Nur fuer Beitraege, die in der App angelegt sind (`source = app`) und bei denen Kanaele gewaehlt wurden. Aus WordPress importierte Beitraege loesen nichts aus (im Parallelbetrieb gewollt). Kein "Free = an alle mit Push" (Sichtbarkeit `members` oder Programm). Mailstrecken-Beitraege gibt es nicht. |
| Push-Ziel: Fenster mit der Neuigkeit, bei Erinnerung/Aufzeichnung der Termin | lea-push.php | `Nachricht->url`, Mitteilungen (Glocke) | vorhanden | |
| Uebersicht "wer hat Push" (Spalte in Benutzerliste) | lea-push.php | Dossier: Anzahl Geraete und Telegram (`Memberships/Pages/Dossier.php`) | teilweise | Nicht als Spalte in der Personenliste des Coach-Bereichs (nicht geprueft ob `MembershipsTable` eine hat). |
| Test-Push an sich selbst | lea-push.php (`lea_push_test`) | - | fehlt | Kein Testknopf, weder im Profil noch im Coach-Bereich. |
| Globaler Schalter Push aus | lea-push.php (`lea_push_aktiv`) | Testbetrieb (`settings.notifications.test_only`, Freigabeliste) | vorhanden (anders) | |
| Alles was als Push geht, geht auch nach Telegram | lea-telegram-anschluss.php | `Notifier::channelsFor`, `TelegramChannel` | vorhanden | Neu mit Knopf "Oeffnen". |
| Telegram verbinden im Profil (Startlink), trennen | lea-telegram-anschluss.php | `TelegramController::verbinden/trennen`, Profil | vorhanden | |
| Telegram-Antwort landet im Gespraech mit Lea | lea-telegram-anschluss.php | `TelegramController::webhook` | vorhanden | |
| Lea antwortet per "Antworten" auf die gemeldete Nachricht, sonst 30 Min im letzten Gespraech, sonst Rueckfrage "An wen?" | lea-telegram-anschluss.php | `TelegramController::webhook`, `telegram_links.settings.map/letzte` | vorhanden | |
| Terminerinnerung auch fuer Personen nur mit Telegram | lea-telegram-anschluss.php | `Notifier::channelsFor`, `Runden::terminErinnerungen` | vorhanden | |
| Versandprotokoll Telegram (wer, wann, ok, ohne Text, letzte 100) | lea-telegram-anschluss.php (`lea_tg_log`) | - | fehlt | Fehler werden nur ins Laravel-Log geschrieben. |
| Ziehen zum Aktualisieren am Handy, im Gespraech nur Nachrichten neu holen | lea-pull.php | `public/js/app.js` ("Ziehen zum Aktualisieren"), `gespraechNachfragen` | vorhanden | |
| Termin-Erinnerungen (Tag 9 Uhr, 1 Stunde vorher) | (lea-termin-erinnerung, ausserhalb G5) | `Runden::terminErinnerungen` | vorhanden | nur der Vollstaendigkeit halber; gehoert zu einer anderen Gruppe. |
| **NEWSLETTER (Mailster), Etappe 11 der Roadmap** | | | | |
| Formulare tragen Anmeldungen in Listen ein (Newsletter, Webinar, Warteliste, Freebie, Live-Abend, Coach-Ausbildung Interessensliste, ADHS-Muetter mit Tag) | lea-mailster-forms.php | - (Roadmap "Anmeldeformular fuer die Website `[app_anmelden tag=...]`") | fehlt | Keine Kontakte ohne Konto, keine Listen oder Tags, kein Formular. Das Klarheitsgespraech fuer Gaeste (`/buchen/gast/erst`) legt ein Gastkonto an, aber keine Newsletter-Einwilligung. |
| Zwei Arten der Anmeldung: Newsletter mit Bestaetigung, Veranstaltung direkt bestaetigt (Zoom-Link sofort) | lea-mailster-forms.php | - | fehlt | Zoom-Link fuer Veranstaltungen kommt in der App ueber die Termin-Seite/Gastbuchung. Ein Live-Abend-Formular ohne Konto gibt es nicht. |
| Einwilligungsnachweis (Datenschutz-Haekchen mit Zeitstempel, IP, Referrer, Herkunft) | lea-mailster-forms.php | - | fehlt | Roadmap: "Einwilligung mit Datum und Herkunft". |
| Eigenes Double-Opt-in mit signiertem Link (HMAC), 30 Tage gueltig, Ziel-Seite bei ok/abgelaufen/Fehler | lea-newsletter-doi.php, lea-mailster-optik.php (Hinweise) | - | fehlt | Roadmap "Double-Opt-in". |
| Newsletter-Kampagne bauen: Vorlage mit Bild, Headline, Text, Button, Vorschautext, Tracking, Webversion | lea-mail-template.php, lea-mail-bild.php, lea-club-mailster.php | - | fehlt | Roadmap "Newsletter aus der App: Vorlage, Empfaenger nach Tag, Mailgun in Wellen, Oeffnungen und Klicks". Rundnachricht (`Rundnachricht`) ist nur fuer Mitglieder. |
| Testversand aus dem Entwurf an frei waehlbare Adressen (bis 5, Adressen merken/vergessen), ungespeicherter Stand warnt | newsletter-testversand.php, lea-testversand-bruecke.php | - | fehlt | Auch in der Rundnachricht gibt es keinen Test an sich selbst. |
| Rueckfrage vor dem Veroeffentlichen: "geht raus an X, nicht rueckgaengig" | newsletter-testversand.php | Rundnachricht, `PostResource` | fehlt | Rundnachricht sendet ohne Bestaetigungsdialog und ohne Vorschau. |
| Mailstrecke: Beitraege der Kategorie "Mailstrecke" sind nur Textquellen fuer Automatik-Mails, gehen nie an die Liste | lea-mailstrecke.php | - | fehlt | Roadmap "Serien (Autoresponder): Tag loest aus, Mails mit Abstand in Tagen". Im Import: nicht geprueft, ob Mailstrecken-Beitraege als Impulse mitkommen (Abgleich nennt "Kampagnen-Beitraege"). |
| Serien/Autoresponder (Freebie-Ablaeufe, Zoom-Link fuer Live-Abend per Liste, Willkommensserie) | lea-mailster-forms.php, lea-club-mailster.php (in Mailster selbst konfiguriert) | - | fehlt | 9 Autoresponder laut Roadmap. Nichts gebaut. |
| Mailster-Bestaetigungs- und Willkommensmails in Leas Vorlage, Du-Ansprache, Texte "Abmelden/abgemeldet/aktualisiert" | lea-mailster-optik.php, lea-mailster-texte.php | - | fehlt | Gehoert zu Newsletter (Abmeldung mit einem Klick, Profil aendern). |
| Newsletter-Formulare/Profil/Abmelde-Seite in Leas Optik | lea-mailster-optik.php | - | fehlt | wie oben. |
| Editor-Knoepfe fuer Mailster (Listen, Ausrichtung, Schriftwahl ...) | lea-mailster-editor-toolbar.php | - | fehlt | Erst relevant, wenn die App einen Newsletter-Editor bekommt. |
| Button-Shortcode `[lea_button]` fuer Website und Kampagnen, Knopf im Editor | lea-button.php, lea-button-mce.js | - | Website | Fuer die Website bleibt WordPress. Im spaeteren Newsletter braucht es einen Button-Baustein. |
| Bei Buchung: Mailster-Liste, Kurs-Tag, Willkommenskampagne je Kurs einmalig (Einzel/Gruppe unterschiedlicher Text) | lea-club-mailster.php | `Zugang::grant` ("Dein Zugang ist da"), `Zugang::welcome`, `WillkommenMail` | teilweise | Die Willkommensmail kommt nur bei neuen Konten (Shop-Webhook) oder per Knopf. Kein Text je Kurs, kein Unterschied 1:1 gegen Gruppe, kein Hinweis "auf Startbildschirm legen, Einfuehrung in 8 Schritten" in der Mail. Bestehende Personen bekommen nur die kurze Mitteilung. |
| Club-News: Kampagne an Liste oder an Kurs-Teilnehmerinnen | lea-club-mailster.php | `PostObserver`, `Rundnachricht`, `Rundsendung` | vorhanden | Mail nur an Personen ohne Push (Kanal-Wahl). |
| Neuer Termin: Mail "Neuer Termin fuer dich" an die Kursteilnehmerinnen | lea-club-mailster.php | `EventObserver::created` (Anlass `termin_neu`) | vorhanden | Mail nur, wenn keine Push/Telegram da ist. |
| **SHOP UND KASSE** | | | | |
| Kasse: Zahlungsart "Kauf auf Rechnung", Beschreibung, Danke-Text | lea-kasse.php | `KaufenController` (`/kaufen/{angebot}`), `kaufen/show/danke.blade.php` | vorhanden | Ohne Stripe nur Rechnung (Roadmap Etappe 10: Karte und Twint fehlen). |
| Kasse: Karte/Twint/PayPal (Zahlarten von WooCommerce/Stripe) | (WooCommerce) | - | fehlt | Roadmap "Kasse mit Stripe", "Abos mit Stripe und Kundenportal". `Verkauf::ZAHLUNGSARTEN` kennt `stripe` schon. |
| Zugang bei Kauf auf Rechnung sofort (Status on-hold) | lea-kasse.php | `WooCommerce::isActive` mit `settings.shop.rechnung_zahlarten`; in der App `Verkaufen` mit `buchhaltung.zugang_bei_rechnung` sofort oder bezahlt | vorhanden | Abgleich 08 sagt "fehlt": das ist behoben. Nur noch pruefen, dass `shop.rechnung_zahlarten = ["lea_rechnung"]` gesetzt ist (nur im JSON der Plattform, nicht in den Coach-Einstellungen). |
| Zustimmung zu AGB, Datenschutz, Widerrufsbelehrung mit Links | lea-kasse.php | `kaufen/show.blade.php` (Haken `agb`) | teilweise | Ein einziger Haken "Ich bestelle zahlungspflichtig ..." ohne Links auf AGB, Datenschutz und Widerruf. |
| Ausdruecklicher Verzicht aufs Widerrufsrecht bei digitalen Inhalten, mit Zeitstempel und Text an der Bestellung, Hinweis in der Bestellmail | lea-kasse.php | - | fehlt | Haken `agb` wird nicht gespeichert. Rechtlich relevant fuer EU-Kaeuferinnen. Weder Zeitstempel noch Text im `verkaeufe`-Datensatz, kein Satz in der Mail. |
| Bestellknopf "Zahlungspflichtig bestellen" | lea-kasse.php | `kaufen/show.blade.php` | teilweise | Knopf heisst "Auf Rechnung kaufen" (bzw. "Kostenlos dabei sein"). Der Hinweis "zahlungspflichtig" steht nur im Haken darueber. |
| Bestellmail: Betreff "Willkommen, X: dein Zugang und deine Rechnung", PDF im Anhang, Link online bezahlen (TWINT/Karte), Link in die App | lea-kasse.php, lea-bexio.php | `RechnungMail` (`mail/rechnung.blade.php`), `Verkaufen::mailen` | vorhanden | Betreff neu "Deine Rechnung: Titel bei Name". Magic Link (7 Tage) statt App-Link. |
| Warme Danke-Seite nach dem Kauf mit "Und jetzt?" | lea-woo-thankyou.php, lea-webinar-danke.php | `kaufen/danke.blade.php` | vorhanden | Webinar-Danke-Seite mit Kalenderlink (Google Calendar) ist Website, nicht Teil der App. |
| Kasse/Warenkorb/Konto Optik, Woo-Texte auf Du, "Strasse", Firmenfeld/Bestellhinweise/Adresszusatz weg | lea-woo-checkout-design.php, lea-woo-texte.php | eigene Kasse mit Name und Mail | Website | Die App-Kasse fragt nur Name und Mail. Keine Rechnungsadresse (Strasse, PLZ, Ort, Land) im Kaufformular: bexio-Kontakt wird ohne Adresse angelegt (`Bexio::kontaktAnlegen`). |
| Kaufknopf-Beschriftung je Produkt, kein "zzgl. Versand" | lea-produkt-verkauf.php | Text des Kaufknopfs im Shortcode `[app_kaufen text=...]` | vorhanden | |
| Preis nach Land: Schweiz/Liechtenstein Franken, sonst Euro (IP, Cloudflare-Header), Umschalten im Browser, gecachte Seiten | lea-preis.php, lea-waehrung.php | `KaufenController::waehrung`, `Api/AngeboteController`, `resources/wordpress/app-angebote.php` | teilweise | Waehrung nur nach URL-Parameter `?w=` oder Mandanten-Vorgabe, keine Erkennung nach Land. Das Website-Plugin zeigt CHF, Euro nur wenn kein CHF-Preis da ist. |
| Feste Waehrung je Kundin ("Rechnet in"), gilt fuer Kasse, Verkauf, Rechnung; wird nach erster Bestellung gemerkt | lea-waehrung.php | - | fehlt | Weder im Profil/Dossier noch beim Anlegen. Im Dossier-Verkauf ist die Waehrung Mandanten-Vorgabe (`tenants.currency`), Lea muss sie jedes Mal waehlen. |
| Euro-Preis je Produkt, sonst 1:1 | lea-waehrung.php | `Offer::preise()` (`preis_chf`, `preis_eur`, Aktionspreise) | vorhanden | Neu auch Aktionspreis mit Enddatum, durchgestrichener Regulaerpreis. Ohne Euro-Preis gilt "kein Preis in EUR", nicht 1:1. |
| Preise frei auf Seiten `[lea_preis]`, `[lea_betrag]`, `[lea_waehrung]` mit Umschalten CHF/EUR | lea-preis.php, lea-waehrung.php | `[app_angebot]`, `[app_kaufen]`, `[app_angebote]` | teilweise | Ein freier Betrag oder Waehrungszeichen im Fliesstext (`[lea_betrag wert=2222]`) hat keinen Ersatz. Preise nur aus Angeboten. |
| `[lea_direktkauf]`: "Du weisst schon, dass du dabei sein willst? Dann buche direkt" nur wenn bezahlbar | lea-preis.php | `[app_kaufen]` | vorhanden | |
| Zahlformat je Waehrung (CHF 1'234.50, Euro 1.234,50) | lea-preisformat.php | `Offer::preisText`, `x-rechnungen` | teilweise | Beides mit Punkt und Apostroph (`1'234.50 EUR`). Euro nicht im deutschen Format. Klein. |
| Verkaufskern `lea_verkauf_anlegen`: Konto ohne Passwort, Bestellung, Zugang, Rechnung, Willkommensmail | lea-kasse.php | `Shop\Verkaufen::verkaufen` (`verkaeufe`) | vorhanden | |
| Person neu anlegen (Vorname, Nachname, Mail, Telefon, Waehrung, Willkommensmail ja/nein) | lea-verkauf.php | `CoacheesController::anlegen`, "Neue Person anlegen" | teilweise | Waehrung fehlt. Kennzeichen "Rechnungskundin" (`lea_rechnungskundin`) fehlt, dafuer Rolle und Notiz. |
| Etwas verkaufen im Dossier: Angebot, Preis, Waehrung, inkl. 1:1-Sitzungen, Rechnung/bereits bezahlt, Notiz, Mail, Sicherheitsabfrage | lea-verkauf.php | `DossierController::zugang`, `coachees/show.blade.php` (`#verkaufen`), `Verkaufen` | vorhanden | Kleine Abweichung: Preis wird beim Waehlen des Angebots nicht aus dem Angebot vorbefuellt (Feld leer = kostenlos, Gefahr, dass ein Verkauf gratis entsteht). Keine Sicherheitsabfrage ("Verkauf abschliessen: Angebot, Preis?"). Zusatz: Laufzeit in Tagen, "kostenlos". |
| Inkludierte 1:1-Sitzungen gutschreiben (an Person und Kurs) | lea-verkauf.php (`lea_vk_sitzungen_geben`) | `Verkaufen::programmeGeben` (`sitzungen_extra`, `sitzungen_gesamt`) | vorhanden | |
| Rechnung gleich erstellen, damit Lea sie sofort sieht | lea-verkauf.php | `Verkaufen::verkaufen` (synchron), Dossier-Reiter Rechnungen | vorhanden | |
| Verkauf als Notiz am Dossier | (neu) | `CoachNote` in `Verkaufen` | vorhanden | Neu, alt nur als Bestellnotiz. |
| **ZUGANG** | | | | |
| Produkt schaltet Kurse frei (Feld am Produkt) | lea-shop-zugang.php | `Offer`, `OfferProduct`, `OfferResource` | vorhanden | Besser: Angebot mit Programmen, Laufzeit, Produkt-IDs aus WooCommerce/Stripe. |
| Zugang nach Bestellung, Entzug bei Storno/Erstattung | lea-shop-zugang.php, lea-zugang-direkt.php | `WooCommerce::order`, `Zugang::grant/revoke`, `/hooks/woocommerce` | vorhanden | |
| Einzelkauf gilt ein Jahr (`zugang_tage`), Club solange das Abo laeuft, Abo aus, Zugang weg; von Hand vergebener bleibt; laengerer wird nie verkuerzt | lea-zugang-direkt.php | `Offer::access_days`, `Entitlement` (source), `WooCommerce::subscription`, `Entitlement::isCurrent` | vorhanden | Kein taeglicher Aufraeum-Lauf noetig, der Ablauf wird beim Lesen geprueft. |
| Eintrag von Hand mit "bis wann" im Profil der Person | lea-zugang-direkt.php | Dossier "Etwas verkaufen oder Zugang geben", `EntitlementsRelationManager` | vorhanden | |
| Zugang zu Kursinhalten nur fuer Gebuchte (Kurs, Modul, Lektion, Ressource), auch ueber die Direktadresse | lea-zugang.php, lea-zugriffsschutz.php | `Gate::authorize('view', $program)`, `ProgramAccess`, Policies | vorhanden | |
| Hinweis "Dieser Kurs gehoert noch nicht zu deinem Programm" statt Fehlerseite | lea-zugang-direkt.php | 403 (Policy) | fehlt | Kein freundlicher Hinweis mit "schreib Lea im Gespraech". Nicht geprueft, wie die 403-Seite aussieht. |
| Kurse in Vorbereitung (`nur_intern`), nur Team und freigegebene Einzelpersonen sehen sie | lea-zugang-direkt.php | `Program.is_published = false`, `ProgramAccess` (Team sieht alles) | teilweise | Freigabe einzelner Testpersonen (`lea_kurs_vorschau`) fehlt. Team sieht Entwuerfe, andere Personen nicht. Testperson kann ueber Zugang zu einem unveroeffentlichten Programm nicht hinein. |
| Coffee & Coaching (pausiert) gehoert allen die mit Lea arbeiten | lea-zugang-direkt.php | - | Altlast | Pausiert. |
| Gratiskurse (`kurs_gratis`) offen fuer alle Mitglieder | lea-zugang-direkt.php | `Program.settings.gratis`, `ProgramAccess` | vorhanden | |
| Gastzugang: nur Termin, Gespraech, Profil, reduziertes Menue, Begruessung | lea-gast.php | Rolle `guest`, Zugang ueber Programme | teilweise | Kein eigenes Menue und keine Begruessungs-Karte fuer Gaeste. Gast sieht das normale Menue mit leeren Bereichen (nicht geprueft, wie leer). |
| Zugang sichern nach dem Gratiskurs: Passwort, Passkey, Google, Apple anbieten | lea-zugang-sichern.php | Profil-Karten "Passkey", "Passwort, freiwillig" | teilweise | Karten nur im Profil, kein Angebot am Ende des Gratiskurses "So kommst du kuenftig wieder herein". Google/Apple im Profil nicht verknuepfbar (siehe Anmeldung). |
| Kuendigung je Plan (kuendbar nur bei Abo-Produkt, Schalter am Plan) | lea-mitgliedschaft-kuendigung.php | `profil.blade.php` Knopf "Abo verwalten" (`shop.account_url`) | teilweise | Der Knopf steht bei jedem Angebot vom Typ Club. Kein Schalter kuendbar/nicht kuendbar je Angebot. Kuendigen laeuft weiter im WooCommerce-Konto, nicht in der App; mit Stripe ist das offen (Roadmap Kundenportal). |
| Meine Buchungen: Karte je Buchung (Art, Balken Sitzungen x von y, Kurswoche x von y, Zugang endet am, seit) | lea-mitgliedschaft-neu.php | `profil.blade.php` "Meine Buchungen", `ProfilController::zugaenge`, `Lage::kontingent` | vorhanden | Ohne "gekauft am und Betrag" je Karte (steht im Rechnungsblock). Knoepfe "Termin buchen" und "Zum Programm" da. |
| Laufende Abos mit Status und naechster Zahlung | lea-mitgliedschaft-neu.php | `profil.blade.php` (Beendet/Zugang bis) | teilweise | "Naechste Zahlung am" fehlt (App kennt das Abo nicht). Status als Zugangsdaten. |
| Weitere Bestellungen (E-Book u. a.) mit Rechnung | lea-mitgliedschaft-neu.php | "Deine Rechnungen" aus bexio | vorhanden | Kommt nun aus bexio, nicht aus Woo. |
| Erklaerung "Was es bei Lea gibt" und Ausblick "Was Lea gerade baut" | lea-mitgliedschaft-neu.php | `/angebote` (`AngeboteController`), Link im Profil | teilweise | Angebote mit Preisen und Kauflink da, aber die vier Erklaerkarten (1:1, Gruppe, Selbstlernkurse, Club) und die Ausblick-Liste nicht. |
| **BEXIO UND RECHNUNGEN** | | | | |
| Verbindung mit bexio: OAuth mit Erneuerung oder Personal Access Token, nur ein Lauf erneuert gleichzeitig | lea-bexio.php | `Shop\Bexio` (`accessToken`, `Cache::lock`), `BuchhaltungController::bexioStart/Rueckkehr`, `/coach/buchhaltung` | vorhanden | Eigener OAuth-Zugang der App; darf nicht mit WordPress geteilt werden (steht im Code). |
| Stammdaten laden (Benutzer, Bankkonten, Ertragskonten, Steuern, Waehrungen, Sprache) mit sinnvollen Vorgaben | lea-bexio.php | `Bexio::stammdaten` | vorhanden | |
| Einstellungen: Konto auf Rechnung, EUR-Konto, Online-Zahlungen auf Konto, Ertragskonto, MWST, Frist, Kopie-Adresse, MWST-Ausweis | lea-bexio.php | `Filament/Coach/Pages/Buchhaltung.php` | teilweise | Im Formular fehlen: separates Konto fuer Euro-Rechnungen und Euro-Zahlungen (der Code liest `bank_account_id_EUR` und `zahlung_bank_account_id_EUR`), MWST-Ausweis (`mwst_type`), Sprache, Ein/Aus-Schalter "Rechnungen erstellen". |
| Kontakt in bexio suchen (Mail) oder anlegen, ID merken | lea-bexio.php | `Bexio::kontaktId/kontaktAnlegen` | teilweise | Kontakt ohne Adresse, ohne Firma/Land (alt: Adresse, Firma, Land wenn vorhanden, Rueckfall ohne Adresse). |
| Rechnung anlegen und ausstellen, Kopie an Lea, Zahlungslink, Nummer | lea-bexio.php | `Bexio::rechnungAnlegen` | vorhanden | Gleiche Logik (Header, Fusszeile, Frist, Waehrung, Bankkonto, Vorlage). |
| Zahlungseingang buchen bei bereits bezahltem Verkauf | lea-bexio.php | `Bexio::zahlungBuchen` | vorhanden | |
| Rechnungs-PDF holen und an Mail haengen | lea-bexio.php | `Bexio::pdf`, `RechnungMail` | vorhanden | |
| Faellt bexio aus: Bestellung/Verkauf laeuft weiter, Rechnung wird mit Stufen nachgeholt (5 Min bis 12 Std) und separat nachgeschickt | lea-bexio.php | `Verkaufen::verkaufen` (Fehler in `settings.rechnung_fehler`, Meldung im Dossier) | fehlt | Kein automatisches Nachholen, keine Nachversand-Mail. Die Rechnung muss von Hand neu angelegt werden (nicht geprueft, wie; im Dossier gibt es keinen Knopf "Rechnung erneut anlegen"). Mail geht ohne PDF raus und ohne Nachtrag. |
| Mail an Lea/Admin nach dem Aufgeben ("Rechnung fehlt fuer Bestellung") | lea-bexio.php | Meldung im Dossier/CoachNote | teilweise | Nur eine Notiz im Dossier, keine Mail. |
| Offene Rechnungen alle 4 Stunden mit bexio abgleichen, bei "bezahlt" Verkauf abschliessen und wartenden Zugang freischalten | lea-bexio.php | `buchhaltung:zahlungen` (stuendlich), `Verkaufen::bezahlt` | vorhanden | Storno in bexio setzt den Verkauf auf "storniert", nimmt aber den Zugang nicht zurueck und benachrichtigt niemanden (alt nur Bestellnotiz). |
| Storno/Erstattung: Hinweis "bitte in bexio stornieren" | lea-bexio.php | - | Altlast | Mit den Woo-Erstattungen (`refunded/cancelled`) entzieht die App den Zugang; Gutschrift in bexio bleibt von Hand. |
| Gesundheitscheck Zugang taeglich, Mail an Admin, wenn Token/OAuth abgelehnt oder Ablauf in 10 Tagen | lea-bexio.php | `Bexio::stand()` zeigt `fehler` auf der Seite `/coach/buchhaltung` | fehlt | Keine Mail und kein Hinweis im Dashboard bei Ablauf. Ausfall zeigt sich erst beim naechsten Verkauf. |
| Rechnung ansehen: PDF ausliefern (nur die Person oder Team) | lea-bexio.php, lea-bexio-kunden.php | `BuchhaltungController::pdf/pdfDossier` | vorhanden | |
| Log der letzten bexio-Meldungen auf der Einstellungsseite (100) | lea-bexio.php | Laravel-Log | fehlt | Nur letzter Fehler (`fehler`). |
| Schluessel fuer KI-Auswertung im bexio-Menue | lea-bexio.php | `settings.ai` (Plattform) | Altlast | Gehoert zur Auswertung, nicht zu bexio. |
| Alle bexio-Rechnungen einer Person (auch ohne Shop), Datum, Titel, Betrag, Status, PDF, "online bezahlen" | lea-bexio-kunden.php | `Bexio::rechnungen`, Profil "Deine Rechnungen", Dossier-Reiter "Rechnungen", `x-rechnungen` | vorhanden | Summe offen je Waehrung im Dossier. |
| Stille Konten: aus allen bexio-Kontakten mit Rechnung ein Konto anlegen, ohne Mail; erscheinen in Leas Uebersicht | lea-bexio-kunden.php (`lea_bxk_import`) | - | fehlt | Wer nur in bexio steht, fehlt in der Personenliste der App. Roadmap kennt das nicht (Etappe 11 "Kontakt" koennte es abdecken). |
| Kundennummer fortlaufend (6-stellig), an Konto gebunden, auf der Rechnung | lea-rechnung.php | bexio vergibt eigene Kontaktnummer | teilweise | Die App fuehrt keine Kundennummer. Ob bexio die Nummer automatisch vergibt und auf der Rechnung zeigt: nicht geprueft. |
| Rechnungslayout "MANTA": Betreff, Datum, Zahlbar bis, Ansprechpartner, Spalten, Total | lea-rechnung.php | bexio-Vorlage (`template_slug`) | vorhanden (anders) | Layout liegt jetzt in bexio, in der App nur der Slug und die Absender-Unterschrift. Ob die bexio-Vorlage Leas Bild hat: nicht geprueft. |
| Zahlungsfrist in Tagen, Ansprechpartner, Anrede einstellbar | lea-rechnung.php | `buchhaltung.bexio.schreiben.frist`, `absender` | vorhanden | |
| Zahlen: Kacheln Umsatz laufendes Jahr, Monat, offen; Ansicht Jahre, 12 Monate, Angebote, beste Kundinnen | lea-zahlen.php | - | fehlt | Keine Summen aus bexio. `Buchhaltung::offen()` gibt es nur je Person. |
| Euro-Rechnungen zum Tageskurs in Franken umrechnen | lea-zahlen.php | - | fehlt | Teil der Zahlen. |
| Im Dossier: Reiter "Zahlen" mit Umsatz gesamt, pro Rechnung, offen, Anteil und Rang, letzte Rechnung, Dabei seit, Wofuer bezahlt, Satz mit Handlungshinweis | lea-zahlen.php | Dossier-Reiter "Rechnungen" | teilweise | Rechnungsliste und offene Summe da. Alle Kennzahlen und Hinweistexte fehlen. Zugriff dort nur fuer Sebastian als Admin (`lea_zl_darf`): neu soll die Inhaberin sehen. |
| **ANMELDUNG** | | | | |
| Mit Google anmelden (nur bestehende Konten) | lea-anmeldedienste.php | `SocialController` (Socialite), `oauth.google` je Mandant | vorhanden | Knopf statt Google-Popup. |
| Mit Apple anmelden (nur bestehende Konten) | lea-apple-login.php | `SocialController`, `oauth.apple` | teilweise | Die App speichert `client_secret` fertig (Apple verlangt ein JWT, das bis 6 Monate gilt), alt wurde es aus Team-ID, Key-ID und .p8 selbst erzeugt. Kein Feld fuer diese Angaben, kein Erneuern (nicht geprueft, ob `socialiteproviders/apple` es aus Konfiguration erzeugen kann). |
| Google/Apple mit dem Konto verknuepfen und trennen (Kennung merken) | lea-anmeldedienste.php, lea-apple-login.php | - | fehlt | Die App gleicht nur ueber die Mailadresse ab. Wer bei Apple die Mail versteckt, kommt nicht hinein ("kein Zugang"), die spezielle Meldung "Du hast deine Adresse verborgen ..." fehlt. |
| Fehlermeldungen je Fall (kein Konto, abgebrochen, keine Verbindung, schon vergeben) | lea-anmeldedienste.php | `SocialController`, `auth/kein-zugang.blade.php` | teilweise | Nur eine allgemeine Meldung und eine Seite "Kein Zugang". |
| Einstellungsseite "Anmeldedienste" (Client-ID, Team, Key, .p8 hochladen) | lea-anmeldedienste.php | Plattform: JSON `settings.oauth.*` (`TenantResource`) | teilweise | Kein Formular, nur JSON-Feld in der Plattform. Kein Hochladen der .p8. |
| Passkey-Anmeldung, Passkeys anlegen/loeschen/benennen, deutsche Texte | lea-login-optionen.php | `PasskeyController`, Profil, `public/js/passkeys.js`, Login-Seite | vorhanden | |
| Magic Link, Passwort optional | (neu) | `MagicLink`, `LoginController` | vorhanden | Besser als alt: Ratelimit, 15 Min, einmalig. |
| Anmeldename gleich Mailadresse | lea-login-name.php | Anmeldung nur ueber Mail | Altlast | |
| Nur Angemeldete im Bereich, Angemeldete weg vom Login, Ruecksprung | lea-member-guard.php | `redirectGuestsTo` mit `weiter`, `auth`-Gruppe | vorhanden | |
| Direktadressen von Kursinhalten fuer Gaeste sperren, REST-Schnittstelle zu | lea-zugriffsschutz.php | Policies, Sanctum-API nur mit Token (`/api/v1`) | vorhanden | |
| Demokonto per Einmal-Link (1 Stunde) fuer Bildschirmfotos | lea-demo-login.php | `AlsController` "Ansehen als" (nur Plattform-Admin) | vorhanden (anders) | Kein Link zum Weitergeben, nur fuer Sebastian im Konto. Reicht fuer Bildschirmfotos. |
| Bruecke Knopf "Zur neuen App" mit signiertem Einmal-Link (60 Sek.) | lea-neueapp-bruecke.php | `Auth\Bridge`, `/sso`, `bridge:secret`, `docs/wordpress/lea-neueapp-bruecke.php` | vorhanden | Gleiches Snippet, gleiche Signatur. |
| Doppelklick-Schutz beim Senden (Kommentare, Antworten, Reflexion), Knopf sperren | lea-doppelschutz.php | Chat sperrt den Knopf (`public/js/app.js`) | teilweise | Serverseitig keine Pruefung "derselbe Text, dieselbe Person, innert 2 Minuten". Kommentare (`KommentarController`, `Kommentare::schreiben`) und Antworten ohne Schutz gegen Doppelabsenden. |
| **WEBSITE-TECHNIK (WordPress bleibt)** | | | | |
| 301 fuer umbenannte Landingpages und alte Adressen (Freebie, Klarheitsgespraech, Ueber Lea, ADHS, Search-Console-Liste) | lea-freebie-redirects.php, lea-altlinks-301.php, lea-umzug.php | - | Website | Bleibt in WordPress. Beim Umschalten (docs/07 4.3) werden `/mitgliederbereich/*` auf die App-Startseite geleitet: Tiefe Links aus alten Mails (Termin, Lektion, Gespraech) landen nur auf der Startseite. Eine Zuordnung alter zu neuen Adressen (`legacy_id`) gibt es nicht. |
| Einstieg unter jedem Beitrag ("Der Anfang" oder Klarheitsgespraech) und ADHS-Einstieg | lea-einstieg-cta.php, lea-adhs-muetter.php | - | Website | Bleibt auf der Website (Shortcode, WordPress). |
| Danke-Seite Live-Abend mit Kalenderlink | lea-webinar-danke.php | - | Website | Nach der Umstellung des Live-Abends auf App-Gastbuchung ist die Mail `GastBuchungMail` mit ics-Datei da. |
| Blogseite: Filter, nur "Free", Karten-Optik | lea-blog-filter-css.php, lea-blog-frei.php, lea-blog-styles.php | - | Website | |
| Marken-CSS, Font Awesome, Elementor-Icons, mobiler Slider | lea-brand-override.php, lea-fontawesome.php, lea-elementor-icons.php, lea-mobile-slider.php | `Tenancy\Branding`, `public/css` | Website | Die App hat eigenes Design. |
| Design-CSS aus Option `lea_design_css`, Stile buendeln | lea-design.php, lea-css-buendel.php | - | Altlast | Nur WordPress-Technik. |
| Externe Bilder nicht vermessen, SVG-Upload erlauben | lea-externe-bilder.php, lea-svg-uploads.php | - | Altlast | |
| WP-Backend: Farbschema, Adminleiste, Kategorien auf Seiten, "Blog / News" | lea-admin-farbschema.php, lea-adminleiste-aufraeumen.php, lea-page-categories.php, lea-beitraege-umbenennen.php | - | Altlast | |
| Sandbox-Ordner sperren, leere Stilllegungsdateien | .htaccess, web.config, index.html, .lea-*.disabled | - | Altlast | |
| Termin-Liste: Badges Live/Aufzeichnung/Aufgabe (Erledigt), Monatstrenner, Filter, "Weitere Termine anzeigen" | lea-ui.php | `termine/index.blade.php` (Badges Live, Aufzeichnung, Dabei; Monatsueberschrift; Filter Kommende/Vergangene/Kurs) | teilweise | Fehlt: Suchfeld in den Terminen, Filter nach Art (Live/Fragentage/Reflexionstage), Begrenzung auf 8 mit "Weitere anzeigen" (neu bis 200 auf einer Seite). |
| Reflexions- und Fragentag als Aufgabe mit Kreis zum Abhaken ("Erledigt"), Fragentag fuehrt in den Kursraum | lea-ui.php | `Event::ALL_DAY_TYPES`, Termine-Liste zeigt nur "ganzer Tag" | fehlt | Kein Abhaken, kein Unterschied Live gegen Aufgabe in der Liste. Kein Sprung vom Fragentag zu den Fragen (`kurse.fragen`). Gehoert eigentlich zur Gruppe Termine, hier mitgeprueft. |
| Mobil-Feinschliff (Umbruch, Bilder, Tabellen) | lea-ui.php | `public/css`, Tailwind | Website | Eigenes Design. |

## Zaehlung

129 Funktionen geprueft: 55 vorhanden (davon 4 anders geloest), 29 teilweise, 26 fehlt, 8 Website (bleibt in WordPress), 11 Altlast.
Nicht geprueft: Aussehen der 403-Seite bei fehlendem Kurszugang, ob bexio die Kundennummer automatisch vergibt, ob die bexio-Vorlage Leas Bild hat, ob die Personenliste im Coach-Bereich eine Push-Spalte hat, Apple-Secret-Erzeugung im Provider.

## Wichtigste Luecken

1. **Kasse rechtlich unvollstaendig.** Kein Verzicht aufs Widerrufsrecht mit Zeitstempel und Text, keine Links auf AGB, Datenschutz und Widerruf, keine Rechnungsadresse. Vor dem Abschalten von WooCommerce noetig (Kasse `KaufenController`, Tabelle `verkaeufe`).
2. **Kartenzahlung und Twint (Stripe) fehlen.** Bis dahin geht Kauf nur auf Rechnung; Abos und Kundenportal (Kuendigung, naechste Zahlung) haengen daran. Roadmap Etappe 10.
3. **Rechnung bei bexio-Ausfall geht verloren.** Kein automatisches Nachholen, kein Nachversand, keine Warnmail; Rechnung muss von Hand neu angelegt werden. Dazu keine Warnung, wenn der bexio-Zugang ablaeuft oder abgelehnt wird (nur Text auf `/coach/buchhaltung`).
4. **Newsletter und Kontakte ganz offen (Etappe 11).** Formulare, Listen/Tags, Einwilligung mit Nachweis, Double-Opt-in, Serien/Autoresponder (Freebie-Ablaeufe, Willkommensserien, Zoom-Link fuer den Live-Abend), Abmeldung, Testversand, Mailstrecken-Texte. Bis Mailster abgeschaltet wird, bleibt alles in WordPress.
5. **Zahlen fuer Lea fehlen.** Umsatz laufendes Jahr, Monat, offen, Angebote, beste Kundinnen, Kennzahlen je Person im Dossier (nur Rechnungsliste und offene Summe da).
6. **Waehrung je Kundin und nach Land.** Kein "Rechnet in" je Person, keine Erkennung CH/LI gegen Rest fuer Preise auf der Website und in der Kasse. Lea muss Waehrung bei jedem Verkauf waehlen, Euro-Kunden bekommen sonst CHF.
7. **Dossier-Verkauf: Preis nicht vorbefuellt.** Leeres Feld heisst kostenlos; keine Sicherheitsabfrage. Ein vergessener Preis erzeugt einen Gratis-Zugang. (`coachees/show.blade.php`)
8. **Apple- und Google-Anmeldung eingeschraenkt.** Kein Verknuepfen/Trennen im Profil (nur Mail-Abgleich), Apple mit versteckter Mail scheitert, Apple-Secret muss von Hand gepflegt werden (laeuft ab), keine Einstellungsseite dafuer. Auch kein Angebot "So kommst du wieder herein" am Ende des Gratiskurses.
9. **Alte Mail-Links nach dem Umschalten.** `/mitgliederbereich/...`-Adressen in verschickten Mails, Kalendern und Push landen nur auf der App-Startseite. Eine Zuordnung alt nach neu (Termin, Lektion, Gespraech) fehlt in docs/07 und im Code.
10. **Stille Konten aus bexio.** Wer nur in bexio steht (Rechnungsempfaengerinnen ohne Kurs), fehlt in Leas Personenliste.
11. **Willkommensmail und Abendmail schlichter als vorher.** Willkommensmail nicht je Kurs/Art, kein Onboarding-Hinweis; Bestehende Personen mit neuem Kurs bekommen nur eine kurze Mitteilung. Abendmail ohne Einzellinks je Eintrag; App-Hinweis "Willst du es merken" nur einmal am Tag.
12. **Test-Push und Kontrolle vor Rundnachrichten.** Kein Test an sich selbst, kein Bestaetigungsdialog vor dem Senden, kein Telegram-Versandprotokoll, kein Mail-Verlauf.
13. **Kuendbarkeit je Angebot und Abo-Infos.** Knopf "Abo verwalten" fuer alle Club-Angebote; Schalter kuendbar/nicht kuendbar und "naechste Zahlung" fehlen. Aendert sich mit Stripe.
14. **Mailrahmen ohne Logo und Fusszeile.** Mails zeigen nur den App-Namen, keine Website und Kontaktadresse.
15. **Kleinigkeiten in Termine und Rechnung.** Reflexions-/Fragentag zum Abhaken, Suche und Art-Filter in den Terminen, Doppelabsende-Schutz bei Kommentaren, bexio-Konto und MWST-Optionen fuer Euro, Kundennummer, "Kurs in Vorbereitung" fuer einzelne Testpersonen.

# 06 Roadmap: in 2 bis 3 Wochen parallel lauffähig

Ziel: Ein Testkurs läuft vollständig in der App, parallel zum bestehenden Mitgliederbereich. Danach Umschalten nach Leas Freigabe.

Jede Etappe endet mit einem Stand, den Lea anschauen kann.

## Etappe 0 - Fundament (erledigt 24.09.)

- [x] Laravel, Livewire, Filament, Socialite installiert
- [x] Mandanten-Kern: tenants, tenant_domains, memberships, Rollen, BelongsToTenant, IdentifyTenant, Tests
- [x] Mandant `lea` mit Domain `app.leawernli.ch`
- [x] Dokumentation, CLAUDE.md
- [x] Plesk: Subdomain, SSL, PHP 8.4, MySQL `lea_app`

## Etappe 1 - Anmelden und Hülle (Tage 1 bis 3)

- [x] Seitenhülle als Blade-Layout (`components/layouts/app`, `auth`) mit Branding aus `tenants.branding` als CSS-Variablen (`App\Tenancy\Branding`), Kartenmass und Schriftleiter aus 04, Leiste unten auf dem Handy
- [x] Tailwind-Standalone eingerichtet, `bin/build-css` (laedt die CLI bei Bedarf, baut `public/css/app.css`)
- [x] Anmeldung: Magic Link als Standard (15 Minuten, einmalig, je Mandant), Passwort optional (im Profil setzbar), Google und Apple über Socialite (Zugangsdaten je Mandant in `settings.oauth`, Knopf erscheint nur mit Zugangsdaten)
- [x] Profil: Name, Handynummer, drei Schalter "Was dich erreicht", Passwort
- [x] Passkeys (WebAuthn, Laragear) mit RP-ID je Mandant (`settings.passkeys.rp_id`, für Lea `leawernli.ch`, damit die Passkeys der Website weitergelten). Übernahme der Website-Passkeys aus `secure_passkeys_webauthns` noch offen (Format prüfen), sonst einmal neu anlegen
- [x] PWA: Manifest je Mandant (`/manifest.webmanifest`), Service Worker ohne Cache. App-Icons: `php84 artisan branding:icons lea /var/www/vhosts/leawernli.ch/httpdocs/wp-content/uploads/lea-app`
- [x] Filament-Panel `coach` (`/coach`, nur owner/team, Ressource Personen) und `plattform` (`/plattform`, nur Plattform-Admin, Ressource Mandanten). Anmeldung läuft über die App, nicht über Filament
- [x] Import 1: Personen und Rollen (`php84 artisan import:wordpress lea --only=users`, wiederholbar, `--dry-run`, `--with-guests`)

Stand nach Etappe 1: Lea kann sich per Link anmelden, sieht Start und Profil, im Coach-Bereich die importierten Personen. Zum Testen auf dem Server: Deploy, `.env` ergänzen (`WP_DB_*`), Import laufen lassen, Sebastian als Plattform-Admin setzen (siehe 05).

## Etappe 2 - Kursraum (erledigt 25.09.)

- [x] Datenmodell Programme (03): programs, program_steps, units, exercises, progress, answers, program_members, offers, offer_products, offer_program, entitlements, notes. Zugriff an einer Stelle: `App\Programs\ProgramAccess` (Gate `view-program`)
- [x] Filament: Programme (Schritte, Einheiten mit Übungsteilen, Mitglieder), Angebote mit Produktzuordnung und Zugängen
- [x] Kursraum: Meine Kurse, Programm, Schritt, Einheit mit Videos, Text, Links, Übungen (Text, Skala, Werte, Haken), Fortschritt, Notiz je Einheit
- [x] Taktung: wöchentlich (unlocks_at je Schritt), alles frei, keine Schritte (1:1)
- [x] Freigabe an die Coachin: einmal "alles" am Anfang oder je Einheit (Workbook-Prinzip)
- [x] Import 2: Kurse, Module, Lektionen, Workbooks (JSON), Fortschritt, Antworten, Zugänge (`--only=programs`)
- [x] Kurse auf dem Server befüllt (Import 25.09.)
- [ ] Mit Lea anschauen, Testkurs festlegen (Kurs-ID in `LEA_NEUEAPP_KURSE` im Bruecken-Snippet, Adressen im Testbetrieb freigeben)

## Etappe 3 - Begleitung (erledigt 25.09.)

- [x] Termine: Liste (kommend, vorbei), Detail mit Zoom, Aufzeichnung (Vimeo), Absagen, "live dabei", "gesehen", Material zum Termin
- [x] Material mit polymorpher Zuordnung (Programm, Schritt, Einheit, Termin, Person), Teilen an Coachees mit Benachrichtigung, Datei-Auslieferung nur mit Zugang
- [x] Aufgaben (eigene und von der Coachin, täglich mit Wochentagen, fällig), Notizen, Reflexion (Woche, teilen), Journal
- [x] Chat 1:1 und Gruppe je Programm: Polling, Gelesen-Haken, Reaktionen, Sprachnachrichten, Dateien, Verweise auf Elemente, schwebender Knopf; Antworten der Coachin per Telegram
- [x] Benachrichtigungen: ein Dienst (`Notifier`) wählt Push, Telegram, Mail; Termin-Erinnerungen (9 Uhr, 60 Minuten vorher), Nachfassen bei Ungelesenem, Aufgaben-Hinweise, Abendmail; Schalter im Profil
- [x] Coachee-Dossier im Coach-Bereich (Programme mit Stand, geteilte Antworten, Aufgaben, Reflexionen, Notizen, Termine, Kontaktknöpfe, Weg ins Gespräch)
- [x] Import 3: Termine, Material, Aufgaben, Notizen, Reflexionen, Journal, Kommentare, Chats, Wochenstruktur (`--only=begleitung`)

## Etappe 4 - Übergang (Code erledigt 25.09., Betrieb offen)

- [x] WooCommerce-Webhook `POST /hooks/woocommerce` (Woo-Signatur) → entitlements, neue Personen mit Mitgliedschaft und Willkommensmail; Abos über `subscription.*`. Produktzuordnung in Filament (Angebote)
- [x] SSO-Brücke `GET /sso?token=` (HMAC, 60 Sekunden, einmalig), `bridge:secret lea`; WordPress-Snippet und Knopf "Zur neuen App" in `docs/07-UEBERGANG.md` (im WordPress noch einzubauen)
- [x] Impulse und Podcast per RSS (`inhalte:feeds`, stündlich) und aus WordPress (`--only=inhalte`), Themenfinder (Themen an allen Inhalten, KI-Zuordnung), Merkliste
- [x] KI-Zusammenfassung von Aufzeichnungen mit Aufgabenvorschlägen (Coach-Bereich und für die Person in ihrer 1:1-Sitzung), Podcast-Aufbereitung, Themenfinder-Texte
- [x] Deploy, `.env` (Mailgun, Redis, WP-DB), `db:seed`, `import:wordpress lea --only=alles`, `push:keys lea`, `bridge:secret lea`, Woo-Webhooks, Bruecke in WordPress, KI-Schluessel (25.09., siehe 05)
- [x] Testbetrieb: Benachrichtigungen nur an freigegebene Adressen, bis umgeschaltet wird
- [ ] Parallelbetrieb mit dem Testkurs, Rückmeldungen einarbeiten
- [x] Passkeys, App-Icons (Kommando), Willkommens-Einführung (8 Schritte, `/willkommen`), Dashboard der Coachin (Kennzahlen, Neues von den Personen, nächste Termine)
- [x] Coach-Bereich: Einstellungen (Aussehen, Absender, Feeds, Telegram-Name), Rundnachricht an alle oder ein Programm, Einladungen mit Anmeldelink
- [x] Startseite mit Tagesüberblick (Neu seit dem letzten Besuch, aktuelle Woche, nächster Termin, Aufgaben, Impuls), Kalender-Abo je Person (webcal) und Termin-Datei

## Etappe 6 - Abgleich mit dem alten Bereich (25.09., siehe 08-ABGLEICH)

- [x] Design wie der alte Mitgliederbereich, Fehler aus dem Abgleich, Kursraum mit Wochenseite, Workbook-Bausteinen und Fragen
- [x] Zeiten in Ortszeit anzeigen und eingeben, UTC speichern
- [x] Coach-Werkzeuge: Ampel, Kommentare, private Notizen, Kontingent, Terminvorschlag, KI-Vorbereitung, Rundnachricht an Einzelne, Wochencheck
- [x] Aufzeichnungs-Wache mit Vimeo, Freigabe mit Mail und Push, neue Termine melden, Zoom-Anwesenheit
- [x] Buchung mit Google-Kalender (eingerichtet, noch ausgeschaltet: `settings.booking.enabled`), Meine Buchungen im Profil
- [x] Rechnungen aus bexio (eigene Verbindung je Mandant, lesend), Buchung verschieben, Buchung für Gäste
- [ ] Verkaufen im Dossier schreibt Kontakt, Rechnung und Zahlung direkt nach bexio (Shop-Bestellungen bleiben bei WooCommerce)

## Etappe 7 - Design, Code und Laravel-Bausteine (26.09.)

- [x] Lesetexte in Serifen, Kursliste einheitlich, keine Karte in der Karte, Kapitelliste mit laufender Markierung, leere Zustaende, Coach-Bereich im Stil der App
- [x] Zeit-Helfer, `Branding::coachName`, Komponenten Termin-Karte und Kommentare, Policies, FormRequests, `EinheitController`, Inline-Styles in Klassen
- [x] Mitteilungen mit Glocke, Suche ueber alles (Scout), Funktionen je Mandant (Pennant), JSON-API v1 (Sanctum), Queue-Worker und Reverb als Systemd-Dienste, Chat in Echtzeit
- [ ] Livewire fuer Workbook-Bausteine (spaeter, wenn es hakt)

## Etappe 8 - Zweiter Rundgang: Nachschlagen, Werkzeuge, Assistent, Coachees (26.09.)

- [x] Nachschlagen mit KI-Suche, Themen nach Gruppen, Vorschau, Merken, Teilen, Sammlungen, Meine Suchen, Mein Archiv
- [x] Werkzeuge fuer die Coach-Ausbildung (Kennzeichen an der Mitgliedschaft, Tuer fuer alle anderen)
- [x] Digitaler Assistent im Coach-Bereich (Fragen mit App-Wissen und Fakten zu Menschen, Themen pruefen, Werkzeuge, Geteiltes)
- [x] Coachees als Seite in der App-Huelle (Ampel, Freigegebenes, Personenkarten, Auskunft)
- [x] Coach-Bereich: Hilfsklassen fuer eigene Ansichten, Neues gebuendelt, Platzhalter in Beitraegen, Marken fuer Entwuerfe

## Etappe 9 - Drei Sichten, Arbeitsplatz, Second Brain, MCP (28.09., siehe 09-SICHTEN-UND-MCP)

- [x] Teilnehmer-Menue verschlankt (Nachschlagen mit Reitern Finden, Themen, Meine Suchen, Mein Archiv, Werkzeuge; Journal ohne Unterpunkte; Mitteilungen nur ueber die Glocke)
- [x] Arbeitsplatz fuer Lea und das Team in der App-Huelle: Heute (Arbeitsliste wie im alten Bereich), Coachees mit "Neue Person anlegen", Dossier mit Reitern (Gespraech, Termine, Kurs, Aufgaben, Freigegeben, Meine Notizen, Vorbereitung), "Etwas verkaufen", Umschalter "Wie eine Teilnehmerin"
- [x] Assistent in der App-Huelle mit Wissensspeicher (Second Brain, Tabelle `wissen`), der Assistent nimmt Gemerktes in seine Antworten
- [x] MCP-Server `/api/mcp` (Streamable HTTP, Sanctum-Token mit Faehigkeit `mcp`), 16 Werkzeuge in `app/Ai/Werkzeuge`, Schluessel im Profil
- [x] Teilnehmer-Sicht 1:1 wie der alte Bereich (Menue, Kopf, Startseite, Community, Ressourcen), "Ansehen als" fuer den Plattform-Admin
- [ ] OAuth fuer ChatGPT-Connectors ohne Token-Eingabe (ChatGPT verlangt bei Custom-Connectors OAuth oder "keine Auth", Claude nimmt Bearer-Token)
- [ ] Leas Assistent selbst mit den Werkzeugen (Anthropic Tool Use), damit "Leg Anna an" auch in der App klappt
- [ ] Bruecke zur Website (leawernli.ch): Kampagnen, Funnels, Seiten anlegen ueber die WordPress-API des Novamira-Plugins

## Etappe 10 - Verkauf und Buchhaltung (29.09., Beschluss: WooCommerce wird abgeloest)

Stand: kein einziger Kauf in WooCommerce, ein laufendes Abo, Lea hat Stripe. Die App verkauft selbst, bexio bleibt die einzige Rechnungsquelle, jeder Mandant hat sein eigenes Buchhaltungssystem.

- [x] Buchhaltung je Mandant (`App\Shop\Buchhaltung`, bexio), Seite `/coach/buchhaltung`, Rechnungen im Profil und im Dossier-Reiter
- [x] Verkaufen im Dossier: Preis, Waehrung, auf Rechnung oder bezahlt, Kontakt, Rechnung und Zahlungseingang in bexio, Mail mit PDF und Anmeldelink, Tabelle `verkaeufe`
- [x] Zugang bei Kauf auf Rechnung: sofort oder erst nach Zahlungseingang (`buchhaltung.zugang_bei_rechnung`), Abgleich stuendlich (`buchhaltung:zahlungen`)
- [x] Angebote oeffentlich: Preise am Angebot (CHF, EUR, Aktion bis Datum), Kauflink `/kaufen/{angebot}` (Kauf auf Rechnung, Gastfluss mit Konto, Herkunft `ref`), Schnittstelle `/api/angebote`, Seite `/angebote` in der App, WordPress-Plugin `resources/wordpress/app-angebote.php` mit `[app_kaufen]`, `[app_angebot]`, `[app_angebote]`
- [x] Kasse mit Stripe je Mandant (Schluessel unter Verbindungen): Checkout (Karte, Twint aus dem Dashboard), Gastfluss mit Konto, Webhook `/hooks/stripe`, Zugang mit der Zahlung, bexio-Quittung, Mail mit PDF und Anmeldelink; Kasse rechtlich: AGB- und Datenschutz-Links, Widerrufsverzicht mit Zeitstempel, Rechnungsadresse
- [x] Abos mit Stripe und Kundenportal (`App\Shop\Abo`, Angebot mit `settings.abo_intervall`, Checkout im Abo-Modus, Verlaengerung mit Quittung, Zahlungsausfall, Kuendigung, Portal unter `/abo/portal`); das bestehende Abo zieht `abo:umziehen` um (docs/07, 4b)
- [ ] Website: Kaufknoepfe auf die App, WooCommerce, German Market, Kasse, Preise, bexio-Modul und Woo-Webhook abschalten (Ablauf in docs/07, 4b; Handarbeit in WordPress beim Umschalten)

## Etappe 11 - Kontakte und Newsletter (Mailster abloesen)

Stand: rund 300 Abonnentinnen, 13 Listen (drei mit Gewicht), 18 Kampagnen, 9 Autoresponder, keine Mailster-Formulare.

- [x] Stufe "Kontakt" unter Gast (`kontakte`, `App\Newsletter\Kontakte`): Mail, Name, Einwilligung mit Zeit, IP, Referrer und Herkunft, Tags statt Listen, kein Login; Mitglieder werden mit Tag `kundin` und Angebots-Slug gefuehrt; Import aus Mailster (`import:wordpress lea --only=kontakte`, Listen als Tags)
- [x] Newsletter aus der App (`/coach/newsletter`, `App\Newsletter\Versand`): Vorlage mit Bild, Headline, Text, Knopf, Vorschautext; Empfaenger nach Tag; Test an bis zu fuenf Adressen; Rueckfrage vor dem Senden; Versand in Wellen (60 pro Minute, `newsletter:lauf wellen`), geplant zur Zeit; Abmeldung mit einem Klick (auch List-Unsubscribe); Oeffnungen und Klicks; Webversion; Double-Opt-in mit signiertem Link (30 Tage)
- [x] Anmeldeformular fuer die Website (`[app_anmelden tag="..." sofort="0"]` in `resources/wordpress/app-angebote.php`, eigene Seite `/newsletter/anmelden`, JSON `POST /api/anmelden`); Freebie geht als Serie mit Schritt "0 Tage"
- [x] Serien (`/coach/serien`, `App\Newsletter\Serien`): Tag loest aus, Mails mit Abstand in Tagen, stuendlich `newsletter:lauf serien`
- [x] Formulare der Website laufen in die App (30.09.): `resources/wordpress/lea-app-anmeldung.php` haengt sich wie die Mailster-Bruecke an `elementor_pro/forms/new_record` und ruft `POST /api/anmelden` (Formularname zu Tag, Veranstaltungen `sofort`, Newsletter-Haken mit Opt-in). Kontakte importiert (313, Hauptliste als Tag `newsletter`), Schluessel in WordPress eingetragen
- [x] Ausgefuelltes Arbeitsbuch als PDF (`/kurse/{slug}/pdf`, `App\Programs\ArbeitsbuchPdf`, dompdf): alle Einheiten mit Frage und Antwort, Notiz je Einheit, Knopf auf der Kursseite und im Baustein "Mitnehmen"
- [x] Weitere Personen am Termin (1.10.): Paar-Coaching, Gast im Call; sehen Termin, Kalender, Aufzeichnung, bekommen Erinnerungen und Freigabe
- [x] Arbeitsliste aufgeraeumt (2.10.): Aufzeichnungen gehen von selbst raus (keine Freigabe mehr, abschaltbar je Mandant ueber `recordings.auto_release_*`), Call der gleich beginnt ganz oben, Lange nichts gehoert, Neu dabei, Zahlen der Woche; Kopf ohne Verwaltungs- und Chat-Icon (Glocke nur mit Ungelesenem), schwebender Chat-Knopf bleibt; Team-Profil ohne Meine Buchungen
- [x] Rundgang Layout und UX (2.10.): Terminliste acht Wochen mit "Alle anzeigen", Tagestermine schmal; Mitteilungen ohne Doppel; Neue Aufgabe und Neue Notiz eingeklappt; Fragen-Filter in einer Zeile; Impulse ohne WordPress-Shortcodes (Knopf wird Link auf `links.website`); leere Zustaende mit sichtbaren Symbolen; Wochenkurs mit "Jetzt dran" oben, Termine nur vier; Coachees ohne doppelten Knopf, Freigaben je Person; Sitzungen im Gespraech eine Zeile; abgesagte eigene Termine aufrufbar statt 403; Tagestermine ohne Dabei-Knoepfe; Startseite zeigt Wochenkurs auch ohne Uebungen
- [x] Newsletter-Baukasten (1.10.): Ueberschrift, Text-Editor, Bild-Upload, Knopf, Trenner, Zitat, Kasten, Angebot; verschieben, klonen, zuklappen; Live-Vorschau neben dem Editor, Vorschau im Browser, Grundlayout mit Musterinhalt unter Einstellungen (Fusszeile, Social-Links); Serien mit demselben Baukasten und Anleitung je Tag
- [x] Mailster abgeschaltet (1.10.): zwei Serien aus den Autorespondern (Willkommen mit Knopf zum Gratiskurs, Aufzeichnung Live-Abend; das E-Book gibt es nicht mehr, das Freebie ist die App mit dem ersten kostenlosen Kurs), Systemmails der Website per `lea-wp-mail-mailgun.php`, alte Abmeldelinks auf `/n/abmelden` (ohne Token, Link per Mail). Am Umzugstag: Kontakte-Import wiederholen, WooCommerce nach docs/07 Punkt 7

## Etappe 12 - Website mit Statamic (Versuch)

Nach dem Umzug der App: eine einfache Website mit Statamic nachbauen (gleicher Stack, Inhalte im Git), um zu sehen, ob daraus ein Modul fuer den Verkauf der App wird. Angebote, Kaufknopf, Buchung und Anmeldeformular kommen aus der App.

## Umschalten (nach Leas Freigabe)

Mitgliederbereich und WooCommerce am 3.10.2026 umgeschaltet (Stand in docs/07, Punkt 4). Danach offen:

- [x] Mailstrecken (3.10.): Serien koennen je Schritt eine Bedingung haben (`Serien::BEDINGUNGEN`: noch nie in der App, Kurs nicht begonnen, nicht fertig, fertig, kein Klarheitsgespraech) und `{anmeldelink}` im Text (frischer Einstiegslink, 7 Tage). Zugang setzt neben dem Angebots-Tag die Tags `gratiskurs` und `1-1-coaching`. Serien bei Lea: Willkommen im Newsletter (3 Mails), Der Anfang: und jetzt? (1 Mail, nur ohne Gespraech), Onboarding 1:1 (4 Mails). Der Gratiskurs "Der Anfang" laeuft ueber die Kasse der App (`/kaufen/der-anfang-wie-gedanken-funktionieren`, Website-Tuer `lea-anfang-app.php`) mit Willkommensmail (settings.willkommen_text) und Strecke (Anstoss nach 2 und 7 Tagen, Goldnuggets am Ende, `settings.strecke`).
- [x] Aufgaben aus der Zusammenfassung (3.10.): Vorschlaege der KI sieht jede am Termin; Teilnehmerin nimmt sie fuer sich, das Team gibt sie allen im Kurs (als Kursaufgabe der Woche, source program) oder der Person der 1:1-Sitzung. Haengt der Termin an einer Kurswoche, haengt die Aufgabe daran (Frist Ende der Woche).
- [x] Kasse: Haekchen fuer den Newsletter (nicht vorausgewaehlt, Einwilligung mit Herkunft und Zeit am Kontakt), Tag `newsletter` startet die Willkommensserie (3.10.).
- [ ] Google-Anmeldung: Client-ID ist aus dem alten Bereich uebernommen, das Client-Secret fehlt (der alte Bereich nutzte Google Identity Services ohne Secret). In der Google Cloud Console beim OAuth-Client das Secret holen und `https://app.leawernli.ch/anmelden/dienst/google/zurueck` als Redirect-URI eintragen, dann in den Einstellungen (oauth.google.client_secret) hinterlegen. Apple ist komplett (Services-ID, Team, Key, .p8), Return-URL `https://app.leawernli.ch/anmelden/dienst/apple/zurueck` bei Apple pruefen.
- [x] MCP per OAuth fuer Claude.ai und ChatGPT (3.10., docs/09): Registrierung, Freigabe in der App, PKCE, Erneuerung.
- [x] Rundnachricht (3.10.): Entwuerfe speichern und laden, Testmail an mich, Protokoll der verschickten (Tabelle `rundnachrichten`), Schalter "Mail an alle".

1. Schreibstopp im alten Mitgliederbereich (Hinweis an Teilnehmerinnen)
2. Letzter Import (idempotent, nur Änderungen)
3. `/mitgliederbereich/*` auf leawernli.ch per 301 auf `app.leawernli.ch` umleiten
4. Sandbox-Module im WordPress abschalten (nicht löschen), nach 30 Tagen aufräumen

## Später

Community, Auswertung, Stripe als zweite Zugangsquelle, Mandanten-Onboarding, Abrechnung der Plattform, native Apps (iOS/Android ueber die API und NativePHP, siehe 09).

## Lueckenliste (30.09., siehe 10-LUECKENLISTE)

Funktion fuer Funktion durch alle 225 alten Dateien plus das JavaScript aus den WordPress-Optionen: 630 Funktionen geprueft, 126 fehlen, 148 teilweise. Reihenfolge und Details in `docs/10-LUECKENLISTE.md`, Tabellen in `docs/luecken/`.

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
- [ ] Rechnungen aus bexio (über WordPress), Buchung verschieben, Buchung für Gäste

## Umschalten (nach Leas Freigabe)

1. Schreibstopp im alten Mitgliederbereich (Hinweis an Teilnehmerinnen)
2. Letzter Import (idempotent, nur Änderungen)
3. `/mitgliederbereich/*` auf leawernli.ch per 301 auf `app.leawernli.ch` umleiten
4. Sandbox-Module im WordPress abschalten (nicht löschen), nach 30 Tagen aufräumen

## Später

Community, Auswertung, Stripe als zweite Zugangsquelle, Mandanten-Onboarding, Abrechnung der Plattform, Reverb für Echtzeit, native Apps.

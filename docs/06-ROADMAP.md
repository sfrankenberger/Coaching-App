# 06 Roadmap: in 2 bis 3 Wochen parallel lauffähig

Ziel: Ein Testkurs läuft vollständig in der App, parallel zum bestehenden Mitgliederbereich. Danach Umschalten nach Leas Freigabe.

Jede Etappe endet mit einem Stand, den Lea anschauen kann.

## Etappe 0 - Fundament (erledigt 24.09.)

- [x] Laravel, Livewire, Filament, Socialite installiert
- [x] Mandanten-Kern: tenants, tenant_domains, memberships, Rollen, BelongsToTenant, IdentifyTenant, Tests
- [x] Mandant `lea` mit Domain `app.leawernli.ch`
- [x] Dokumentation, CLAUDE.md
- [ ] Plesk: Subdomain, SSL, MySQL (Sebastian)

## Etappe 1 - Anmelden und Hülle (Tage 1 bis 3)

- [ ] Seitenhülle als Blade-Layout mit Branding aus `tenants.branding` (CSS-Variablen), Kartenmass und Schriftleiter aus 04
- [ ] Tailwind-Standalone einrichten, `bin/build-css`
- [ ] Anmeldung: Magic Link als Standard, Passwort optional, Google und Apple über Socialite (Zugangsdaten je Mandant in settings)
- [ ] Passkeys (WebAuthn), RP-ID konfigurierbar je Mandant; Übernahme aus `secure_passkeys_webauthns` prüfen
- [ ] PWA: Manifest je Mandant, Service Worker, App-Icons (liegen in WordPress unter `uploads/lea-app/`)
- [ ] Filament-Panel `coach` (Pfad `/coach`) mit Zugriff nur für owner/team, Panel `plattform` nur für Plattform-Admin
- [ ] Import 1: Personen und Rollen (`php84 artisan import:wordpress lea --only=users`)

## Etappe 2 - Kursraum (Tage 4 bis 8)

- [ ] Datenmodell Programme (03), Filament-Ressourcen dafür
- [ ] Kursraum für Teilnehmerinnen: Übersicht, Woche/Schritt, Einheit mit Video und Text, Übungen, Fortschritt
- [ ] Taktung: wöchentlich freischalten, alles frei, keine Schritte (1:1)
- [ ] Notizen, Aufgabenknöpfe je Übung, Freigabe einmal am Anfang (Workbook-Prinzip)
- [ ] Import 2: Kurse, Module, Lektionen, Workbook
- [ ] **Testkurs** in der App vollständig befüllt

## Etappe 3 - Begleitung (Tage 9 bis 13)

- [ ] Termine mit Kalender, Zoom-Link, Aufzeichnung (Vimeo), Anhänge
- [ ] Ressourcen mit polymorpher Zuordnung, Teilen an Coachees
- [ ] Aufgaben mit Fälligkeit und Erinnerung
- [ ] Chat 1:1 und Gruppe, Sprachnachrichten, Gelesen-Haken, Reaktionen, schwebender Knopf
- [ ] Benachrichtigungen: Mail (Mailgun), Web Push, Telegram, Abendmail, Schalter im Profil
- [ ] Coachee-Dossier für Lea
- [ ] Import 3: Termine, Ressourcen, Aufgaben, Notizen, Reflexionen, Chats

## Etappe 4 - Übergang (Tage 14 bis 18)

- [ ] WooCommerce-Webhook → entitlements; Angebote und Produktzuordnung in Filament
- [ ] SSO-Brücke aus WordPress (signierter Link), Knopf "Zur neuen App" im alten Mitgliederbereich nur für Testkurs-Teilnehmerinnen
- [ ] Impulse und Podcast (per RSS aus WordPress), Themenfinder, Merkliste
- [ ] KI-Zusammenfassung von Terminen, Aufgaben aus Zusammenfassung
- [ ] Parallelbetrieb mit dem Testkurs, Rückmeldungen einarbeiten

## Umschalten (nach Leas Freigabe)

1. Schreibstopp im alten Mitgliederbereich (Hinweis an Teilnehmerinnen)
2. Letzter Import (idempotent, nur Änderungen)
3. `/mitgliederbereich/*` auf leawernli.ch per 301 auf `app.leawernli.ch` umleiten
4. Sandbox-Module im WordPress abschalten (nicht löschen), nach 30 Tagen aufräumen

## Später

Community, Auswertung, Zoom-Anwesenheit, Stripe als zweite Zugangsquelle, Mandanten-Onboarding, Abrechnung der Plattform, Reverb für Echtzeit, native Apps.

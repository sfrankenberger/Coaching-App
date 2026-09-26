# 02 Architektur

## Überblick

```
leawernli.ch (WordPress)                 app.leawernli.ch (Laravel, Mandant "lea")
- öffentliche Seiten, Elementor          - Mitgliederbereich / App (PWA)
- WooCommerce: Kauf, Rechnung, Bexio  -->  Webhook: Zugang freischalten
- Mailster: Newsletter                   - Coach-Bereich (Filament) für Lea + Team
- Freebie-Anmeldung                  -->  Einladung / Magic Link
                                         - Benachrichtigungen: Mail, Push, Telegram
                                         
weitere-coachin.ch  (beliebig)       -->  app.weitere-coachin.ch (gleiche App, Mandant 2)
plattform-domain (später)                - Plattform-Verwaltung (Sebastian)
```

## Mandantenfähigkeit

**Modell: eine Datenbank, Spalte `tenant_id`.**

- Warum nicht eine DB pro Mandant: Auf Plesk muss jede Datenbank von Hand bzw. über `plesk bin` mit Root angelegt werden, Migrationen laufen dann N-mal, Backups und Importe werden aufwendiger. Bei der zu erwartenden Grösse (einige Dutzend Coaches, je einige Hundert Personen) bringt Trennung auf DB-Ebene keinen Vorteil, der das aufwiegt.
- Absicherung stattdessen im Code: Trait `BelongsToTenant` mit globalem Scope. Ohne gesetzten Mandanten liefert jede Abfrage **nichts** (bewusst streng). Tests pro Tabelle.
- Später umziehbar: Weil jede Zeile `tenant_id` trägt, lässt sich ein grosser Mandant jederzeit in eine eigene Datenbank exportieren.

**Mandant ermitteln:** Middleware `IdentifyTenant` liest den Hostnamen und sucht ihn in `tenant_domains`. Jeder Mandant kann mehrere Domains haben (eigene Subdomain `app.coachin.ch`, später auch eine Plattform-Subdomain `coachin.plattform.ch`). Unbekannte Domain = 404.

**Personen:** Die Tabelle `users` ist plattformweit (eine Mailadresse = eine Person). Die Zugehörigkeit zu einem Mandanten samt Rolle steht in `memberships`. So kann dieselbe Person bei zwei Coaches Kundin sein, ohne zwei Konten.

**Sessions und Cookies:** Pro Host getrennt (kein gemeinsames Cookie über Mandanten hinweg). `SESSION_DOMAIN` leer lassen.

**Rollen je Mandant:** `owner` (Coachin), `team` (Assistenz), `client` (1:1), `member` (Kurs/Club), `guest` (Gratis-Einstieg). Dazu plattformweit `is_platform_admin`.

**Branding je Mandant:** `tenants.branding` (Farben, Schriften, Logo, App-Icon, App-Name). Wird als CSS-Variablen in die Seitenhülle geschrieben, PWA-Manifest wird pro Mandant dynamisch erzeugt (`/manifest.webmanifest`).

## Stack

| Bereich | Wahl | Begründung |
|---|---|---|
| Framework | Laravel 13, PHP 8.4 | aktuell, PHP 8.4 auf dem Server vorhanden |
| Oberfläche Teilnehmerinnen | Livewire 4 + Blade + Alpine | serverseitig, kein Node nötig, passt zu PHP-Know-how |
| Coach-Bereich | Filament 5, Panel `coach` mit Filament-Tenancy | Lea und Andrea pflegen Kurse, Termine, Ressourcen, Personen ohne Eigenbau-Werkstatt |
| Plattform-Verwaltung | Filament 5, Panel `plattform` | Mandanten, Domains, Pläne (nur Sebastian) |
| CSS | Tailwind 4 Standalone-CLI | kein Node auf dem Server |
| Datenbank | MySQL (Plesk), vorerst SQLite bis die DB angelegt ist | |
| Queue / Cache | Redis (läuft lokal), Fallback database | |
| Scheduler | Crontab des Systembenutzers, jede Minute `schedule:run` | kein Supervisor ohne Root |
| Queue-Worker | Cron jede Minute `queue:work --stop-when-empty --max-time=55` | ohne Supervisor tragfähig bei unserem Volumen |
| Echtzeit (Chat) | Start: Livewire-Polling (`wire:poll.5s` nur im offenen Gespräch). Später Laravel Reverb, sobald ein Dauerprozess per systemd läuft | |
| Mail | Mailgun (wie bisher), Absender je Mandant aus Settings | |
| Push | Web Push (VAPID), Kanal `laravel-notification-channels/webpush`, Schlüssel je Mandant | |
| Telegram | eigener Notification-Channel, Bot-Token je Mandant | Portierung von `novamira-telegram` |
| Login | Magic Link (Standard, ohne Passwort), Passkeys, Google, Apple (Socialite), Passwort optional | Leas Einstieg "ohne Passwort" |
| KI | Laravel AI SDK, Anthropic, Opus | Zusammenfassungen, Aufgaben aus Zusammenfassung, Impuls-Texte |
| Video | Vimeo-Einbettung (wie beschlossen), Bunny-Altbestand nur verlinken | |
| Dateien | `storage/app/tenants/{id}`, Auslieferung über signierte Routen | |

## Anmeldung und Übergang von WordPress

- **Magic Link (umgesetzt):** `POST /anmelden/link` legt in `login_tokens` einen Hash ab (15 Minuten, einmalig, `tenant_id`), die Mail geht mit Absender aus `settings.mail` raus. `GET /anmelden/{token}` meldet an und setzt "angemeldet bleiben". Ein Link gilt nur auf der Domain, auf der er angefordert wurde. Unbekannte Adressen und Personen ohne Mitgliedschaft im Mandanten bekommen keine Mail, die Antwortseite ist trotzdem dieselbe. Angemeldete ohne aktive Mitgliedschaft werden von `EnsureMembership` abgemeldet.
- **Passwort (optional):** `users.password` ist nullable, wer will, setzt im Profil eines. Google/Apple über Socialite mit Zugangsdaten aus `settings.oauth.{google,apple}`; ohne Zugangsdaten erscheint kein Knopf.

- **Passkeys:** Auf leawernli.ch registrierte Passkeys sind an die Relying Party `leawernli.ch` gebunden. Setzt die App die RP-ID ebenfalls auf `leawernli.ch` (erlaubt, weil `app.` eine Subdomain ist), lassen sich die gespeicherten öffentlichen Schlüssel aus `secure_passkeys_webauthns` übernehmen. **Muss geprüft werden** (Format der gespeicherten Credentials). Sonst: einmal neu anlegen, per Magic Link ist das ein Klick.
- **Brücke während des Parallelbetriebs:** WordPress erzeugt für eingeloggte Personen einen signierten, 60 Sekunden gültigen Link (`/sso?token=...`, HMAC mit gemeinsamem Geheimnis `WP_BRIDGE_SECRET`). Die App prüft Signatur, Ablauf und Einmaligkeit und meldet die Person an. So kommt man aus dem alten Mitgliederbereich ohne erneutes Anmelden in die App.
- **Zugänge aus WooCommerce:** Woo-Webhook `order.updated` (Status completed/processing) und Subscription-Statuswechsel an `POST /hooks/woocommerce/{tenant}` mit Woo-Signatur. Die App ordnet Produkt-IDs Angeboten zu (Tabelle `offer_products`) und legt `entitlements` an bzw. beendet sie.

## Benachrichtigungen

Ein zentraler Dienst entscheidet pro Person und Anlass den Kanal (heute in `lea-termin-erinnerung.php`, `lea-abendmail.php`, `lea-push.php`, `lea-was-kommt-an.php` verteilt):
Push, wenn vorhanden, sonst Mail. Telegram zusätzlich, wenn verbunden. Abendmail als Sammelmail nur an Personen ohne Push und nur bei Neuem. Drei Schalter im Profil (Termin-Erinnerungen, Abendmail, Aufgaben-Erinnerungen). Alles als Laravel Notifications mit `via()`, damit es testbar ist.

## Was bewusst später kommt

Community (Beiträge, Kommentare, Reaktionen), Auswertung/Zahlen, Zoom-Anwesenheit, Mandanten-Selbstregistrierung und Abrechnung der Plattform, Reverb, native Apps (Capacitor).

# CLAUDE.md - Arbeitsanweisung für Claude Code

Dieses Repository ist die **mandantenfähige Coaching-App** (Arbeitstitel offen). Erster Mandant ist Lea Wernli (`app.leawernli.ch`). Die App ersetzt den Eigenbau-Mitgliederbereich auf leawernli.ch (WordPress, `wp-content/novamira-sandbox/`, 203 Dateien).

Lies vor jeder grösseren Arbeit: `docs/01-ENTSCHEIDUNG.md`, `docs/02-ARCHITEKTUR.md`, `docs/03-DATENMODELL.md`. Stand und nächste Schritte: `docs/06-ROADMAP.md`.

## Auftraggeber und Arbeitsweise

- Sebastian (Entwickler, entscheidet technisch), Lea Wernli (Coachin, Mandantin 1), Andrea (Leas Assistentin, Rolle `team`).
- Sebastian schreibt knapp ("weiter"). Routineschritte selbständig machen und Ergebnis melden, nicht bei jedem Schritt nachfragen.
- Lea will keine technischen Rückfragen, lieber benutzbare Zwischenstände zum Testen.
- **Niemals den langen Gedankenstrich (Em-Dash) verwenden**, weder im Code noch in Texten noch in Antworten. Bindestrich, Komma, Punkt oder Klammern.
- Oberfläche auf Deutsch, Schweizer Schreibweise (kein ß, "ss"). Leas Ton: Du, warm, kurz.
- Grundregel aus dem WordPress-Umbau: **neben dem Bestehenden bauen, umschalten wenn das Neue läuft, nie umgekehrt.** leawernli.ch bleibt live, bis hier alles trägt.

## Server

- Plesk-Server `srv.iksf.de` (Ubuntu 24, Strato). Systembenutzer `leawernli.ch`, Gruppe `psacln`.
- Projektpfad: `/var/www/vhosts/leawernli.ch/app.leawernli.ch`, Webroot `public/`. Datenbank MySQL `lea_app`.
- PHP: immer **`/opt/plesk/php/8.4/bin/php`** (das `php` im PATH ist 8.3 CLI). Alias-Vorschlag: `alias php84=/opt/plesk/php/8.4/bin/php`.
- Composer: `/opt/plesk/php/8.4/bin/php /opt/psa/var/modules/composer/composer.phar`
- **Kein Node auf dem Server.** Tailwind über die Standalone-CLI (`bin/tailwindcss`), Filament und Livewire bringen fertige Assets mit. Wenn doch Vite nötig ist: lokal bauen und `public/build` committen.
- Redis läuft lokal (`redis-cli ping` = PONG). Prefix `REDIS_PREFIX=lea_app_` setzen, der Server hat mehrere Seiten.
- Last kann hoch sein (Load > 7). Lange Läufe (composer, Importe) mit `nohup nice -n 10 ... &` und Log lesen.
- WordPress-Datenbank ist als zweite, **nur lesende** Verbindung `wordpress` konfiguriert (Tabellenpräfix `sWmOBXK94_`). Niemals in die WordPress-DB schreiben.

## Befehle

```bash
php84 artisan migrate
php84 artisan db:seed
php84 artisan test
php84 artisan tenant:create <slug> "<Name>" <domain> --owner-email=...
php84 artisan user:platform-admin <email>              # Plattform-Admin (nur Sebastian)
php84 artisan import:wordpress lea --only=users --dry-run -v   # auch programs, begleitung, inhalte, alles
php84 artisan push:keys lea                            # VAPID-Schluessel fuer Web Push
php84 artisan bridge:secret lea                        # Geheimnis der SSO-Bruecke (in WordPress eintragen)
php84 artisan benachrichtigungen:runde termine         # Laeufe (termine, nachfassen, aufgaben, abendmail), sonst Scheduler
php84 artisan inhalte:feeds lea                        # Impulse und Podcast per RSS, sonst stuendlich
php84 artisan aufzeichnungen:wache lea                 # Vimeo-Aufzeichnungen zuordnen, Abschrift, Zusammenfassung (alle 15 Min)
php84 artisan zoom:anwesenheit lea --trocken           # wer war im Zoom-Call (stuendlich, ohne --trocken setzt es "live dabei")
php84 artisan api:token <email>                        # Token fuer die JSON-API /api/v1 (Sanctum)
php artisan test --parallel                            # lokal, mit paratest etwa dreimal so schnell
php84 artisan themen:profil lea --limit=20             # Themenfinder per KI
php84 artisan branding:icons lea <ordner>              # App-Icons uebernehmen
php84 artisan filament:assets                          # nach Filament-Updates, laeuft im Deploy
bin/build-css                                          # Tailwind bauen (bin/build-css --watch beim Entwickeln)
```

Weitere Ordner: `app/Programs` (Zugriff, Fortschritt, Begleitung), `app/Chat`, `app/Notifications` (Notifier, Kanaele, Runden), `app/Shop` (Zugaenge, WooCommerce), `app/Content` (Feeds, Inhalte, Themen), `app/Ai` (Anthropic, Summarizer), `app/Import/WordPress`, `app/Coach` (Lage/Ampel, Kommentare, Wochencheck), `app/Recordings` (Vimeo, Wache, Freigabe), `app/Zoom` (Anwesenheit), `app/Booking` (Google-Kalender, Verfuegbarkeit, Buchung). Einstellungen je Mandant in `tenants.settings`: `mail`, `oauth`, `push.vapid`, `telegram`, `shop.webhook_secret`, `bridge.secret`, `feeds`, `ai`, `import.wordpress`, `vimeo.token`, `recordings`, `zoom`, `google.service_account`, `booking` (`enabled` schaltet die Buchung frei), `wochencheck.haken`.

Wer darf was: Policies in `app/Policies` (`Gate::authorize('view', $program)` usw.), Regeln liegen in `ProgramAccess`, `Begleitung`, `Chat`. Formulare pruefen `app/Http/Requests`. Funktionen je Mandant ueber Pennant (`Feature::for($tenant)->active('buchung')`, definiert in `AppServiceProvider`). Mitteilungen in der App (Glocke): `App\Models\Mitteilung`, jede `Nachricht` aus dem `Notifier` landet dort. Suche: Scout mit Datenbank-Treiber (`Searchable` an Unit, Resource, Event, Post, PodcastEpisode). Chat in Echtzeit: Reverb (`MessageSent` auf `gespraech.{id}`), ohne Reverb fragt der Browser alle 5 Sekunden nach.

Schriften: `font_body` (Mono) fuer Titel, Zeilen und Meta, `font_read` (Serifen) fuer Lesetexte ab drei Zeilen (`.prose-app`, `.lesetext`), `font_heading` fuer Ueberschriften. Coach-Bereich nutzt dieselben Variablen (`public/css/coach.css`).

Zeiten: in der Datenbank UTC, Modelle lesen in der Zeitzone des Mandanten (`Ortszeit` in `BelongsToTenant`), Abfragen binden immer UTC (`UtcBindings`). Beim Testen Zeiten also in UTC in die DB schreiben und in Ortszeit erwarten.

Anmeldung: Magic Link (`App\Auth\MagicLink`, Tabelle `login_tokens`), Passwort optional, Google/Apple je Mandant, Passkeys (Laragear WebAuthn, RP-ID je Mandant ueber `TenantWebAuthn`), Bruecke aus WordPress (`App\Auth\Bridge`). Seitenhülle: `resources/views/components/layouts/app.blade.php`, Branding-Variablen aus `App\Tenancy\Branding`. Coach-Bereich: Filament-Panel `coach` unter `app/Filament/Coach`, Plattform unter `app/Filament/Plattform`. Filament-Routen laufen nicht über die `web`-Gruppe, darum steht `IdentifyTenant` in jedem Panel als erste Middleware.

## Harte Regeln für Mandantenfähigkeit

1. **Jede Tabelle mit Mandantendaten hat `tenant_id`** (foreignId, indexiert) und das Modell nutzt `App\Tenancy\Concerns\BelongsToTenant`. Keine Ausnahme ohne Kommentar, warum.
2. Eindeutigkeiten immer zusammen mit `tenant_id` (`unique(['tenant_id','slug'])`).
3. Jobs, Kommandos, Mails, Scheduler: Mandant explizit setzen mit `app(CurrentTenant::class)->run($tenant, fn () => ...)`. Jobs bekommen die `tenant_id` als Eigenschaft, nie aus dem globalen Zustand eines anderen Requests.
4. Nichts Lea-Spezifisches im Code. Namen, Texte, Farben, Mail-Absender, Preise, Kategorien gehören in `tenants.settings` / `tenants.branding` oder in Inhaltstabellen. Wenn im Code "Lea" steht, ist das ein Fehler (Ausnahme: Seeder und Import).
5. Dateien unter `storage/app/tenants/{tenant_id}/...`.
6. Für jede neue mandantenfähige Tabelle ein Test, der zeigt, dass Mandant B die Daten von A nicht sieht.
7. Rollen hängen an `memberships` (pro Mandant), nicht am User. Plattform-Admin (`users.is_platform_admin`) nur für Sebastian.

## Stack

Laravel 13, PHP 8.4, Livewire 4, Filament 5 (Coach-Bereich und Plattform-Verwaltung), Tailwind 4 (Standalone), Socialite (Google, Apple), Passkeys, Web Push, Telegram, Mailgun, Laravel AI SDK (Anthropic, Qualität vor Tempo: Opus). Details in `docs/02-ARCHITEKTUR.md`.

## Qualität

- Nach jeder Änderung `php84 artisan test`. Vor Deploy `php84 -l` auf geänderte Dateien.
- Migrationen nie nachträglich ändern, wenn sie auf dem Server gelaufen sind. Neue Migration.
- Commits klein, deutsche Commit-Messages im Imperativ ("Kursraum: Fortschritt speichern").
- Keine Secrets in Git. `.env` bleibt auf dem Server.

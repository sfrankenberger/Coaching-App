# 05 Betrieb auf dem Plesk-Server

## Was bereits erledigt ist (24.09.2026)

- Laravel 13.33 unter `/var/www/vhosts/leawernli.ch/app.leawernli.ch` (Systembenutzer `leawernli.ch`)
- Pakete: livewire/livewire 4.4, filament/filament 5.8, laravel/socialite 5.31
- Mandanten-Kern, Migrationen, Seeder für Mandant `lea`, Tests
- Git-Repository initialisiert (lokal, noch ohne Remote)
- MySQL-Datenbank `lea_app` (Plesk, Benutzer `lea_app`), migriert und geseedet
- Subdomain `app.leawernli.ch` mit Dokumentstamm `/app.leawernli.ch/public`, PHP 8.4 FastCGI, Let's Encrypt
- WordPress-DB als lesende Zweitverbindung `wordpress` in `.env`
- DNS: `app.leawernli.ch` zeigt bereits auf den Server (85.214.17.182, Cloudflare ohne Proxy)
- Crontab des Benutzers: Scheduler jede Minute

## Plesk-Einrichtung (erledigt 24.09.2026)

Subdomain, SSL, PHP 8.4 und Datenbank sind eingerichtet. Zur Sicherheit liegt zusaetzlich eine `.htaccess` im Projektordner, die alles nach `public/` umleitet, falls der Dokumentstamm je wieder auf den Projektordner zeigt. SSH fuer den Systembenutzer ist aus (/bin/false), gearbeitet wird ueber GitHub oder den Plesk-Root-Zugang.

Cloudflare-Proxy fuer `app` aus lassen (DNS only), die Subdomain nicht ueber LiteSpeed/QUIC.cloud cachen.

## Arbeitsweise mit Claude Code

**Empfohlen: GitHub + lokale Entwicklung + Deploy per Skript**

1. Privates Repository anlegen (z. B. `sfrankenberger/coaching-app`), auf dem Server `git remote add origin ...` und pushen (Deploy-Key für den Server).
2. Lokal klonen, mit Laravel Herd laufen lassen (`lea.localhost` ist im Seeder schon als Domain eingetragen, `TENANCY_FALLBACK=lea` für lokal).
3. Claude Code arbeitet lokal, testet, committet, pusht.
4. Auf dem Server `./deploy.sh` (pull, composer install --no-dev, migrate --force, Tailwind bauen, `filament:assets`, optimize).

**Alternative: Claude Code direkt auf dem Server per SSH.** Schneller für den Anfang, aber ohne lokale Tests und mit Risiko für die Live-Seite. Nur, wenn SSH für den Benutzer freigeschaltet ist.

## Erste Schritte nach dem ersten Deploy (Etappe 1)

```bash
PHP=/opt/plesk/php/8.4/bin/php
cd /var/www/vhosts/leawernli.ch/app.leawernli.ch
./deploy.sh                                   # pull, composer, migrate, CSS, Filament-Assets
$PHP artisan db:seed                          # Mandant lea mit Branding und Import-Zuordnung (idempotent)
$PHP artisan user:platform-admin mail@sfrankenberger.com --name="Sebastian"
$PHP artisan import:wordpress lea --only=users --dry-run -v   # erst schauen
$PHP artisan import:wordpress lea --only=users                # dann schreiben
```

In der `.env` muessen dafuer stehen: `WP_DB_DATABASE`, `WP_DB_USERNAME`, `WP_DB_PASSWORD`, `WP_DB_PREFIX=sWmOBXK94_` (nur lesend), `MAIL_*` fuer Mailgun, `TENANCY_FALLBACK` leer, `APP_LOCALE=de_CH`, `APP_FALLBACK_LOCALE=de` (sonst spricht Filament Englisch), `REDIS_PREFIX=lea_app_`.

Anmelden: `https://app.leawernli.ch/anmelden`, Mailadresse eingeben, Link aus der Mail klicken. Wer keine Mitgliedschaft im Mandanten hat, bekommt keinen Link (die Seite verraet das nicht). Google/Apple: Client-ID und Secret unter `/plattform` beim Mandanten in `settings.oauth.google` bzw. `settings.oauth.apple` eintragen, Redirect-URL ist `https://app.leawernli.ch/anmelden/dienst/google/zurueck` bzw. `.../apple/zurueck`.

Import-Zuordnung (in `tenants.settings.import.wordpress`, vom Seeder gesetzt): WordPress-ID 2 = owner, Rollen `administrator` und `lea_redaktion` = team, Kurszugang (`lea_zugaenge` gueltig oder Relation 13) = member, Rest = guest (nur mit `--with-guests`). Uebernommen werden Name, Mailadresse, Telefon (`lea_telefon`), die drei Schalter (`lea_te_aus`, `lea_am_aus`, `lea_ap_erinnerung_aus`) und ob die Einfuehrung gesehen wurde. Passwoerter werden nicht uebernommen, der Magic Link ersetzt sie. Der Import ist wiederholbar und ueberschreibt nichts, was die Person in der App selbst geaendert hat.

## Cron (bereits eingetragen)

```
* * * * * cd /var/www/vhosts/leawernli.ch/app.leawernli.ch && /opt/plesk/php/8.4/bin/php artisan schedule:run >> /dev/null 2>&1
```

Queue-Worker wird im Scheduler selbst gestartet (`routes/console.php`): `queue:work --stop-when-empty --max-time=50`, einmal pro Minute, ohne Überlappung.

## Backups

Plesk-Backup der Domain leawernli.ch erfasst den Ordner `app.leawernli.ch` automatisch mit. Die MySQL-Datenbank `lea_app` ebenfalls, sobald sie über Plesk angelegt ist.

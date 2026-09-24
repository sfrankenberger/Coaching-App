# 05 Betrieb auf dem Plesk-Server

## Was bereits erledigt ist (24.09.2026)

- Laravel 13.33 unter `/var/www/vhosts/leawernli.ch/app.leawernli.ch` (Systembenutzer `leawernli.ch`)
- Pakete: livewire/livewire 4.4, filament/filament 5.8, laravel/socialite 5.31
- Mandanten-Kern, Migrationen, Seeder für Mandant `lea`, Tests
- Git-Repository initialisiert (lokal, noch ohne Remote)
- Datenbank vorerst SQLite (`database/database.sqlite`)
- WordPress-DB als lesende Zweitverbindung `wordpress` in `.env`
- DNS: `app.leawernli.ch` zeigt bereits auf den Server (85.214.17.182, Cloudflare ohne Proxy)
- Crontab des Benutzers: Scheduler jede Minute

## Was Sebastian in Plesk einmal machen muss (ca. 10 Minuten)

Diese Schritte brauchen Plesk-Adminrechte, die der Systembenutzer nicht hat.

1. **Subdomain anlegen:** Websites & Domains → leawernli.ch → Subdomain hinzufügen
   - Name: `app`
   - Dokumentstamm: `/app.leawernli.ch/public` (Ordner existiert bereits, nicht überschreiben lassen)
2. **PHP-Einstellungen der Subdomain:** PHP 8.4, FPM über nginx. `memory_limit` 256M, `upload_max_filesize` und `post_max_size` 64M (Sprachnachrichten, PDFs).
3. **nginx-Zusatzanweisung** (Apache & nginx → Zusätzliche nginx-Anweisungen), damit Laravel-Routen funktionieren, falls nginx direkt ausliefert:
   ```
   location / { try_files $uri $uri/ /index.php?$query_string; }
   ```
   (Bei Apache-Proxy reicht die mitgelieferte `public/.htaccess`.)
4. **SSL:** SSL/TLS-Zertifikate → Let's Encrypt für `app.leawernli.ch`.
5. **Datenbank:** Datenbanken → Neu: Name `lea_app`, Benutzer `lea_app`. Zugangsdaten in `.env` eintragen (`DB_CONNECTION=mysql`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), dann `php84 artisan migrate --seed`.
6. **Cache-Ausnahme:** Die Subdomain nicht über LiteSpeed/QUIC.cloud cachen. Cloudflare-Proxy für `app` aus lassen (DNS only).
7. **SSH für Claude Code:** Entweder SSH-Zugang für den Systembenutzer `leawernli.ch` (Plesk → Hosting-Zugriff → SSH: /bin/bash), oder Arbeit über ein privates GitHub-Repository (siehe unten).

## Arbeitsweise mit Claude Code

**Empfohlen: GitHub + lokale Entwicklung + Deploy per Skript**

1. Privates Repository anlegen (z. B. `sfrankenberger/coaching-app`), auf dem Server `git remote add origin ...` und pushen (Deploy-Key für den Server).
2. Lokal klonen, mit Laravel Herd laufen lassen (`lea.localhost` ist im Seeder schon als Domain eingetragen, `TENANCY_FALLBACK=lea` für lokal).
3. Claude Code arbeitet lokal, testet, committet, pusht.
4. Auf dem Server `./deploy.sh` (pull, composer install --no-dev, migrate --force, optimize, Tailwind bauen).

**Alternative: Claude Code direkt auf dem Server per SSH.** Schneller für den Anfang, aber ohne lokale Tests und mit Risiko für die Live-Seite. Nur, wenn SSH für den Benutzer freigeschaltet ist.

## Cron (bereits eingetragen)

```
* * * * * cd /var/www/vhosts/leawernli.ch/app.leawernli.ch && /opt/plesk/php/8.4/bin/php artisan schedule:run >> /dev/null 2>&1
```

Queue-Worker wird im Scheduler selbst gestartet (`routes/console.php`): `queue:work --stop-when-empty --max-time=50`, einmal pro Minute, ohne Überlappung.

## Backups

Plesk-Backup der Domain leawernli.ch erfasst den Ordner `app.leawernli.ch` automatisch mit. Die MySQL-Datenbank `lea_app` ebenfalls, sobald sie über Plesk angelegt ist.

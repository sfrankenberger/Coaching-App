<?php

use App\Auth\MagicLink;
use Illuminate\Support\Facades\Schedule;

// Der Queue-Worker laeuft als Systemd-Dienst (lea-app-queue.service), siehe docs/05-BETRIEB-PLESK.md

// Verbrauchte und abgelaufene Anmelde-Links aufraeumen
Schedule::call(fn () => MagicLink::prune())->daily()->name('login-tokens-aufraeumen');

// Benachrichtigungen (Zeiten in der Zeitzone des Servers, Mandanten-Zeitzone im Lauf selbst)
Schedule::command('benachrichtigungen:runde termine')->everyTenMinutes()->withoutOverlapping();
Schedule::command('benachrichtigungen:runde nachfassen')->everyTenMinutes()->withoutOverlapping();
Schedule::command('benachrichtigungen:runde aufgaben --wann=morgen')->dailyAt('08:00');
Schedule::command('benachrichtigungen:runde aufgaben --wann=abend')->dailyAt('18:00');
Schedule::command('benachrichtigungen:runde abendmail')->dailyAt('19:30');
Schedule::command('benachrichtigungen:runde fragen')->dailyAt('16:50');   // nur am Sammeltag des Mandanten

// Inhalte: Feeds holen, geplante Beitraege melden
Schedule::command('inhalte:feeds')->hourly()->withoutOverlapping();
// Parallelbetrieb: geplante WordPress-Importe je Mandant (settings.import.wordpress.schedule)
Schedule::command('import:geplant')->hourlyAt(17)->withoutOverlapping(50)->runInBackground();
Schedule::command('inhalte:veroeffentlichen')->everyTenMinutes()->withoutOverlapping();

// Aufzeichnungen: nach jedem Termin auf Vimeo suchen, Abschrift und Zusammenfassung, dann melden
Schedule::command('aufzeichnungen:wache')->everyFifteenMinutes()->withoutOverlapping(30)->runInBackground();

// Zoom: wer war im Call, danach "live dabei"
Schedule::command('zoom:anwesenheit')->hourlyAt(25)->withoutOverlapping(30)->runInBackground();

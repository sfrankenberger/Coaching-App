<?php

use App\Ai\Anthropic;
use App\Auth\MagicLink;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Der Queue-Worker laeuft als Systemd-Dienst (lea-app-queue.service), siehe docs/05-BETRIEB-PLESK.md

// Verbrauchte und abgelaufene Anmelde-Links aufraeumen
Schedule::call(fn () => MagicLink::prune())->daily()->name('login-tokens-aufraeumen');

// Benachrichtigungen (Zeiten in der Zeitzone des Servers, Mandanten-Zeitzone im Lauf selbst)
Schedule::command('benachrichtigungen:runde termine')->everyTenMinutes()->withoutOverlapping();
Schedule::command('benachrichtigungen:runde nachfassen')->everyTenMinutes()->withoutOverlapping();
Schedule::command('benachrichtigungen:runde aufgaben --wann=morgen')->dailyAt('08:00');
Schedule::command('benachrichtigungen:runde aufgaben --wann=abend')->dailyAt('18:00');
Schedule::command('benachrichtigungen:runde punkt')->everyFiveMinutes()->withoutOverlapping();   // "Jetzt dran" zur Uhrzeit der Aufgabe
Schedule::command('benachrichtigungen:runde abendmail')->dailyAt('19:30');
Schedule::command('benachrichtigungen:runde fragen')->dailyAt('16:50');   // nur am Sammeltag des Mandanten
Schedule::command('benachrichtigungen:runde strecke')->dailyAt('10:10');  // Gratiskurs: Anstoss nach 2 und 7 Tagen

// Inhalte: Feeds holen, geplante Beitraege melden
Schedule::command('inhalte:feeds')->hourly()->withoutOverlapping();
// Parallelbetrieb: geplante WordPress-Importe je Mandant (settings.import.wordpress.schedule)
Schedule::command('import:geplant')->hourlyAt(17)->withoutOverlapping(50)->runInBackground();
Schedule::command('inhalte:veroeffentlichen')->everyTenMinutes()->withoutOverlapping();
// Podcast: neue Folgen abschreiben und mit Kapiteln versehen; Themenfinder nachts ueber alles Neue
Schedule::command('podcast:aufbereiten')->hourlyAt(35)->withoutOverlapping(50)->runInBackground();
Schedule::call(function () {
    foreach (Tenant::where('is_active', true)->get() as $t) {
        if (Anthropic::configured($t)) {
            Artisan::call('themen:profil', ['tenant' => $t->slug, '--limit' => 30]);
        }
    }
})->dailyAt('03:15')->name('themen-profil')->withoutOverlapping(120);

// Aufzeichnungen: nach jedem Termin auf Vimeo suchen, Abschrift und Zusammenfassung, dann melden
Schedule::command('aufzeichnungen:wache')->everyFifteenMinutes()->withoutOverlapping(30)->runInBackground();

// Zoom: wer war im Call, danach "live dabei"
Schedule::command('zoom:anwesenheit')->hourlyAt(25)->withoutOverlapping(30)->runInBackground();

// Buchhaltung: offene Rechnungen mit bexio abgleichen, wartende Zugaenge freischalten
Schedule::command('buchhaltung:zahlungen')->hourlyAt(40)->withoutOverlapping(30)->runInBackground();

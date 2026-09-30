<?php

return [
    /*
    | Datensicherungen: die App ruft die Server-Schnittstelle nur auf, sie sichert
    | nicht selbst. Das Skript auf dem Server (Root, per sudo ohne Passwort fuer den
    | Systembenutzer der App) kennt drei Befehle und antwortet mit JSON:
    |
    |   app-restore list <app>                      Staende: [{id, time, source, pre_restore}]
    |   app-restore status <app>                    {state: idle|running|done|failed, started_at, finished_at, snapshot, log}
    |   app-restore restore <app> <id> <source>     startet die Wiederherstellung im Hintergrund
    |
    | Quelle ist "local" (Server) oder "remote" (HiDrive). Waehrend der Wiederherstellung
    | ist die App im Wartungsmodus (503), die Seite fragt den Status trotzdem weiter ab.
    */

    // Name der App im Skript (nicht der Mandant: das Skript sichert die ganze Installation)
    'app' => env('BACKUP_RESTORE_APP', 'lea'),

    // Vollstaendiger Aufruf ohne Unterbefehl. Leer = Seite zeigt "nicht eingerichtet".
    'command' => env('BACKUP_RESTORE_COMMAND', 'sudo -n /usr/local/bin/app-restore'),

    // Sekunden, die die Liste der Staende zwischengespeichert wird (0 = jedes Mal frisch)
    'cache_seconds' => (int) env('BACKUP_RESTORE_CACHE', 60),

    // Zeitzone fuer die Anzeige der Staende
    'timezone' => env('BACKUP_RESTORE_TIMEZONE', 'Europe/Vienna'),

    // Lokaler und entfernter Stand aus demselben Lauf liegen wenige Sekunden auseinander:
    // innerhalb dieser Spanne werden sie als ein Stand mit zwei Quellen gezeigt.
    'merge_seconds' => (int) env('BACKUP_RESTORE_MERGE_SECONDS', 90),

    // Bestaetigungscode per Mail: Gueltigkeit in Minuten und erlaubte Fehlversuche
    'code_minutes' => 10,
    'code_attempts' => 5,
];

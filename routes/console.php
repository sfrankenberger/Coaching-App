<?php

use App\Auth\MagicLink;
use Illuminate\Support\Facades\Schedule;

/*
| Kein Supervisor auf dem Plesk-Server: der Worker laeuft minuetlich ueber den
| Scheduler und beendet sich, wenn die Queue leer ist.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

// Verbrauchte und abgelaufene Anmelde-Links aufraeumen
Schedule::call(fn () => MagicLink::prune())->daily()->name('login-tokens-aufraeumen');

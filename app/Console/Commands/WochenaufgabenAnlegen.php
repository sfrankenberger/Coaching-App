<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\Tenant;
use App\Programs\Wochenaufgabe;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Reflexion und Frage der laufenden Kurswoche fuer alle im Kurs anlegen: php84 artisan wochenaufgaben:anlegen [lea]
 * Laeuft taeglich frueh im Scheduler, damit die Aufgaben am Wochenstart da sind, bevor jemand die Woche oeffnet.
 */
class WochenaufgabenAnlegen extends Command
{
    protected $signature = 'wochenaufgaben:anlegen {tenant? : Kuerzel, sonst alle aktiven}';

    protected $description = 'Reflexions- und Fragentag der laufenden Woche als Aufgaben fuer alle im Kurs anlegen';

    public function handle(CurrentTenant $current, Wochenaufgabe $wa): int
    {
        $tenants = $this->argument('tenant')
            ? Tenant::where('slug', $this->argument('tenant'))->get()
            : Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $current->run($tenant, function () use ($tenant, $wa) {
                foreach (Program::where('pacing', 'weekly')->with('steps')->get() as $p) {
                    $n = $wa->anlegenFuerAlle($p);
                    if ($n) {
                        $this->line("{$tenant->slug}: {$p->title}: {$n} neu");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}

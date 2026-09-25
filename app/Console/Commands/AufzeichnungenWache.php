<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Recordings\MaterialVideo;
use App\Recordings\Wache;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Aufzeichnungen nach den Terminen suchen und aufbereiten: php84 artisan aufzeichnungen:wache [lea]
 * Laeuft alle 15 Minuten, nur fuer Mandanten mit Vimeo-Zugang (settings.vimeo.token).
 */
class AufzeichnungenWache extends Command
{
    protected $signature = 'aufzeichnungen:wache {tenant? : Kuerzel, sonst alle aktiven}';

    protected $description = 'Vimeo-Aufzeichnungen den Terminen zuordnen, Abschrift und Zusammenfassung holen';

    public function handle(CurrentTenant $current): int
    {
        $tenants = $this->argument('tenant')
            ? Tenant::where('slug', $this->argument('tenant'))->get()
            : Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            if (blank($tenant->setting('vimeo.token'))) {
                continue;
            }
            try {
                $b = $current->run($tenant, fn () => app(Wache::class)->lauf());
                $this->line("{$tenant->slug}: {$b['zugeordnet']} zugeordnet, {$b['abschriften']} Abschriften, {$b['zusammenfassungen']} Zusammenfassungen, {$b['freigegeben']} freigegeben, {$b['gemeldet']} gemeldet");
                $m = $current->run($tenant, fn () => app(MaterialVideo::class)->lauf());
                if (array_sum($m)) {
                    $this->line("{$tenant->slug}: Material {$m['fertig']} aufbereitet, {$m['wartet']} wartet auf die Textspur, {$m['offen']} ohne Abschrift");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("{$tenant->slug}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

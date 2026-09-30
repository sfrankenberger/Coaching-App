<?php

namespace App\Console\Commands;

use App\Content\PodcastAufbereiten;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/** Podcastfolgen ohne Abschrift oder Kapitel aufbereiten: php84 artisan podcast:aufbereiten [lea] [--limit=3] */
class PodcastAufbereitenCommand extends Command
{
    protected $signature = 'podcast:aufbereiten {tenant? : Kuerzel, sonst alle aktiven} {--limit=3}';

    protected $description = 'Abschrift, Kapitel, FAQ und Schlagworte fuer neue Podcastfolgen';

    public function handle(CurrentTenant $current): int
    {
        $tenants = $this->argument('tenant') ? Tenant::where('slug', $this->argument('tenant'))->get() : Tenant::where('is_active', true)->get();
        foreach ($tenants as $tenant) {
            $b = $current->run($tenant, fn () => app(PodcastAufbereiten::class)->lauf((int) $this->option('limit')));
            if (array_sum($b)) {
                $this->line("{$tenant->slug}: {$b['abschriften']} Abschriften, {$b['kapitel']} mit Kapiteln, {$b['offen']} offen");
            }
        }

        return self::SUCCESS;
    }
}

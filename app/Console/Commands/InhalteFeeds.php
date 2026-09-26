<?php

namespace App\Console\Commands;

use App\Content\FeedImport;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Feeds aller (oder eines) Mandanten holen: php84 artisan inhalte:feeds [lea]
 * Laeuft stuendlich im Scheduler. Feeds in tenants.settings.feeds.
 */
class InhalteFeeds extends Command
{
    protected $signature = 'inhalte:feeds {tenant? : Kuerzel, sonst alle aktiven}';

    protected $description = 'Impulse und Podcastfolgen per RSS holen';

    public function handle(CurrentTenant $current): int
    {
        $tenants = $this->argument('tenant')
            ? Tenant::where('slug', $this->argument('tenant'))->get()
            : Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            if (empty($tenant->setting('feeds'))) {
                continue;
            }
            $stats = $current->run($tenant, fn () => (new FeedImport($tenant))->run());
            $this->line("{$tenant->slug}: {$stats['beitraege']} Beiträge, {$stats['folgen']} Folgen");
            foreach ($stats['fehler'] as $f) {
                $this->warn('  '.$f);
            }
        }

        return self::SUCCESS;
    }
}

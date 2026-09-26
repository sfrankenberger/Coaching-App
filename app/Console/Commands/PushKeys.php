<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\WebPushChannel;
use Illuminate\Console\Command;

/** VAPID-Schluessel fuer Web Push je Mandant anlegen: php84 artisan push:keys lea */
class PushKeys extends Command
{
    protected $signature = 'push:keys {tenant}';

    protected $description = 'Erzeugt die VAPID-Schluessel eines Mandanten (falls noch keine da sind)';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $keys = WebPushChannel::ensureKeys($tenant);
        $this->info("Public Key: {$keys['public']}");

        return self::SUCCESS;
    }
}

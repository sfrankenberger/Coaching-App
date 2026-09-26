<?php

namespace App\Console\Commands;

use App\Auth\Bridge;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Geheimnis der SSO-Bruecke je Mandant: php84 artisan bridge:secret lea
 * Das Geheimnis kommt in WordPress in die wp-config.php (LEA_APP_BRIDGE_SECRET),
 * Beispiel-Snippet in docs/07-UEBERGANG.md.
 */
class BridgeSecret extends Command
{
    protected $signature = 'bridge:secret {tenant} {--neu : Vorhandenes Geheimnis ersetzen}';

    protected $description = 'Zeigt oder erzeugt das Geheimnis der SSO-Bruecke eines Mandanten';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $secret = Bridge::secret($tenant);
        if (! $secret || $this->option('neu')) {
            $secret = Str::random(48);
            $settings = $tenant->settings ?? [];
            data_set($settings, 'bridge.secret', $secret);
            $tenant->forceFill(['settings' => $settings])->save();
            $this->info('Neues Geheimnis gesetzt.');
        }
        $this->line("Bridge-Secret: {$secret}");
        $this->line('SSO-Adresse: https://'.$tenant->primaryDomain().'/sso?token=...');

        return self::SUCCESS;
    }
}

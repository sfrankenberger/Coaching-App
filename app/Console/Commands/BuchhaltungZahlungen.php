<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Verkauf;
use App\Shop\Buchhaltung;
use App\Shop\Verkaufen;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Zahlungsabgleich mit der Buchhaltung: php84 artisan buchhaltung:zahlungen [lea]
 * Offene Verkaeufe mit Rechnung werden nachgeschaut; meldet die Buchhaltung "bezahlt", wird der
 * Verkauf bezahlt gesetzt und ein wartender Zugang freigeschaltet. Laeuft stuendlich.
 */
class BuchhaltungZahlungen extends Command
{
    protected $signature = 'buchhaltung:zahlungen {tenant? : Kuerzel, sonst alle aktiven}';

    protected $description = 'Offene Rechnungen mit der Buchhaltung abgleichen';

    public function handle(CurrentTenant $current): int
    {
        $tenants = $this->argument('tenant') ? Tenant::where('slug', $this->argument('tenant'))->get() : Tenant::where('is_active', true)->get();
        foreach ($tenants as $tenant) {
            $current->run($tenant, function () use ($tenant) {
                $b = Buchhaltung::fuer($tenant);
                if (! $b || ! $b->verbunden()) {
                    return;
                }
                $offen = Verkauf::where('status', 'offen')->whereNotNull('rechnung_id')->with(['user', 'offer', 'entitlement'])->get();
                foreach ($offen as $v) {
                    try {
                        $status = $b->rechnungStatus((int) $v->rechnung_id);
                    } catch (RuntimeException $e) {
                        $this->warn("{$tenant->slug}: Rechnung {$v->rechnung_nr}: ".$e->getMessage());

                        continue;
                    }
                    if ($status === 'bezahlt') {
                        app(Verkaufen::class)->bezahlt($v);
                        $this->line("{$tenant->slug}: {$v->user?->name}, {$v->title}: bezahlt");
                    } elseif ($status === 'storniert') {
                        $v->forceFill(['status' => 'storniert'])->save();
                        $this->line("{$tenant->slug}: {$v->user?->name}, {$v->title}: storniert");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}

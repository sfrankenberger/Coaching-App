<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use App\Zoom\Anwesenheit;
use App\Zoom\Zoom;
use Illuminate\Console\Command;

/**
 * Wer war im Zoom-Call dabei: php84 artisan zoom:anwesenheit [lea] [--trocken]
 * Laeuft stuendlich fuer Mandanten mit Zoom-Zugang (settings.zoom) und settings.zoom.attendance.
 */
class ZoomAnwesenheit extends Command
{
    protected $signature = 'zoom:anwesenheit {tenant? : Kuerzel, sonst alle aktiven} {--trocken : nur anzeigen, nichts setzen}';

    protected $description = 'Zoom-Teilnehmerlisten holen und "live dabei" setzen';

    public function handle(CurrentTenant $current): int
    {
        $tenants = $this->argument('tenant')
            ? Tenant::where('slug', $this->argument('tenant'))->get()
            : Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $current->run($tenant, function () use ($tenant) {
                if (! app(Zoom::class)->konfiguriert() || (! $this->argument('tenant') && ! $tenant->setting('zoom.attendance', true))) {
                    return;
                }
                $a = app(Anwesenheit::class);
                foreach ($a->termine() as $e) {
                    $b = $a->abgleich($e, (bool) $this->option('trocken'));
                    $this->line("{$tenant->slug}: {$e->starts_at->format('d.m. H:i')} {$e->title}: ".($b['fehler'] ?? count($b['gesetzt']).' sicher, '.count($b['unsicher']).' über den Namen, '.count($b['fremd']).' unbekannt'));
                    foreach (['unsicher', 'uebersprungen', 'fremd'] as $k) {
                        foreach ($b[$k] as $z) {
                            $this->line("    {$k}: {$z}");
                        }
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}

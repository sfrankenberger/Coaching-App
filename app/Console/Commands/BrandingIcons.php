<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * App-Icons eines Mandanten uebernehmen: php84 artisan branding:icons lea /pfad/zu/uploads/lea-app
 * Kopiert icon-*.png nach public/tenants/{id}/ und traegt sie ins Branding ein
 * (icon_url = groesstes Icon, icons = alle mit Groesse aus dem Dateinamen).
 */
class BrandingIcons extends Command
{
    protected $signature = 'branding:icons {tenant} {dir : Ordner mit icon-192.png, icon-512.png, icon-maskable-512.png ...}';

    protected $description = 'App-Icons aus einem Ordner uebernehmen und ins Branding eintragen';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $dir = rtrim($this->argument('dir'), '/');
        if (! is_dir($dir)) {
            $this->error("Ordner nicht gefunden: {$dir}");

            return self::FAILURE;
        }
        $ziel = public_path("tenants/{$tenant->id}");
        if (! is_dir($ziel)) {
            mkdir($ziel, 0755, true);
        }
        $icons = [];
        $groesstes = null;
        foreach (glob($dir.'/*.png') ?: [] as $file) {
            $name = basename($file);
            if (! preg_match('~(\d{2,4})~', $name, $m)) {
                continue;
            }
            copy($file, $ziel.'/'.$name);
            $size = (int) $m[1];
            $src = "/tenants/{$tenant->id}/{$name}";
            $icons[] = array_filter(['src' => $src, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => str_contains($name, 'maskable') ? 'maskable' : null]);
            if (! str_contains($name, 'maskable') && ($groesstes === null || $size > $groesstes[0])) {
                $groesstes = [$size, $src];
            }
            $this->line("  {$name} -> {$src}");
        }
        if ($icons === []) {
            $this->warn('Keine icon-*.png gefunden.');

            return self::FAILURE;
        }
        $branding = $tenant->branding ?? [];
        $branding['icons'] = $icons;
        $branding['icon_url'] = $groesstes[1];
        $tenant->forceFill(['branding' => $branding])->save();
        $this->info(count($icons).' Icons eingetragen, icon_url = '.$groesstes[1]);

        return self::SUCCESS;
    }
}

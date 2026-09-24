<?php

namespace App\Console\Commands;

use App\Import\WordPress\ProgramsImport;
use App\Import\WordPress\UsersImport;
use App\Import\WordPress\WordPressSource;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Import aus WordPress (nur lesend auf der Verbindung "wordpress").
 *
 *   php84 artisan import:wordpress lea --only=users
 *   php84 artisan import:wordpress lea --only=users --with-guests --dry-run
 */
class ImportWordPress extends Command
{
    protected $signature = 'import:wordpress {tenant : Kuerzel des Mandanten}
        {--only=users : Was importiert wird, kommagetrennt (users, programs)}
        {--with-guests : Auch Konten ohne Kurszugang als Gast anlegen}
        {--dry-run : Nur zeigen, nichts schreiben}';

    protected $description = 'Importiert Daten aus der WordPress-Datenbank in den angegebenen Mandanten';

    public function handle(CurrentTenant $current): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();
        if (! $tenant) {
            $this->error('Mandant nicht gefunden: '.$this->argument('tenant'));

            return self::FAILURE;
        }

        $parts = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        return $current->run($tenant, function () use ($tenant, $parts) {
            foreach ($parts as $part) {
                match ($part) {
                    'users' => $this->users($tenant),
                    'programs', 'kurse' => $this->programs($tenant),
                    default => $this->warn("Unbekannter Teil: {$part} (moeglich: users, programs)"),
                };
            }

            return self::SUCCESS;
        });
    }

    protected function users(Tenant $tenant): void
    {
        $this->info('Personen und Rollen'.($this->option('dry-run') ? ' (Probelauf)' : ''));

        $import = new UsersImport($tenant, new WordPressSource, (bool) $this->option('with-guests'), (bool) $this->option('dry-run'));
        $stats = $import->run(fn (string $line) => $this->line('  '.$line, verbosity: 'v'));

        $this->table(['gelesen', 'angelegt', 'aktualisiert', 'uebersprungen'], [[
            $stats['gelesen'], $stats['angelegt'], $stats['aktualisiert'], $stats['uebersprungen'],
        ]]);
        foreach ($stats['rollen'] as $role => $n) {
            $this->line("  {$role}: {$n}");
        }
    }

    protected function programs(Tenant $tenant): void
    {
        $this->info('Kurse, Module, Lektionen, Arbeitsbuecher, Fortschritt und Zugaenge'.($this->option('dry-run') ? ' (Probelauf)' : ''));

        $import = new ProgramsImport($tenant, new WordPressSource, (bool) $this->option('dry-run'));
        $stats = $import->run(fn (string $line) => $this->line('  '.$line, verbosity: 'v'));

        $this->table(['Programme', 'Schritte', 'Einheiten', 'Uebungsteile', 'Fortschritt', 'Antworten', 'Zugaenge', 'Mitglieder'], [[
            $stats['programme'], $stats['schritte'], $stats['einheiten'], $stats['uebungsteile'], $stats['fortschritt'], $stats['antworten'], $stats['zugaenge'], $stats['mitglieder'],
        ]]);
        foreach ($stats['hinweise'] as $h) {
            $this->warn('  '.$h);
        }
    }
}

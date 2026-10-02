<?php

namespace App\Console\Commands;

use App\Import\WordPress\BegleitungImport;
use App\Import\WordPress\InhalteImport;
use App\Import\WordPress\KontakteImport;
use App\Import\WordPress\ProgramsImport;
use App\Import\WordPress\PushImport;
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
        {--only=users : Was importiert wird, kommagetrennt (users, programs, begleitung, inhalte, push, kontakte) oder alles}
                            {--schluessel : Beim Teil push auch die VAPID-Schluessel aus WordPress uebernehmen}
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
        if ($parts === ['alles'] || $parts === ['all']) {
            $parts = ['users', 'programs', 'begleitung', 'inhalte'];
        }

        return $current->run($tenant, function () use ($tenant, $parts) {
            foreach ($parts as $part) {
                match ($part) {
                    'users' => $this->users($tenant),
                    'programs', 'kurse' => $this->programs($tenant),
                    'begleitung', 'termine' => $this->begleitung($tenant),
                    'inhalte', 'impulse' => $this->inhalte($tenant),
                    'push' => $this->push($tenant),
                    'kontakte', 'mailster' => $this->kontakte($tenant),
                    default => $this->warn("Unbekannter Teil: {$part} (moeglich: users, programs, begleitung, inhalte, push, kontakte, alles)"),
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

    protected function push(Tenant $tenant): void
    {
        $this->info('Push-Abos'.($this->option('schluessel') ? ' und VAPID-Schluessel' : '').($this->option('dry-run') ? ' (Probelauf)' : ''));

        $import = new PushImport($tenant, new WordPressSource, (bool) $this->option('schluessel'), (bool) $this->option('dry-run'));
        $stats = $import->run(fn (string $line) => $this->line('  '.$line, verbosity: 'v'));

        $this->table(['personen', 'abos', 'neu', 'ohne_konto', 'schluessel'], [[
            $stats['personen'], $stats['abos'], $stats['neu'], $stats['ohne_konto'], $stats['schluessel'] ? 'ja' : 'nein',
        ]]);
    }

    protected function kontakte(Tenant $tenant): void
    {
        $this->info('Kontakte aus Mailster (Abonnentinnen, Listen als Tags)'.($this->option('dry-run') ? ' (Probelauf)' : ''));

        $import = new KontakteImport($tenant, new WordPressSource, (bool) $this->option('dry-run'));
        $stats = $import->run(fn (string $line) => $this->line('  '.$line, verbosity: 'v'));

        $this->table(['gelesen', 'angelegt', 'aktualisiert', 'abgemeldet', 'listen'], [[$stats['gelesen'], $stats['angelegt'], $stats['aktualisiert'], $stats['abgemeldet'], $stats['listen']]]);
        foreach ($stats['hinweise'] as $h) {
            $this->warn('  '.$h);
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

    protected function begleitung(Tenant $tenant): void
    {
        $this->info('Termine, Material, Aufgaben, Notizen, Reflexionen, Journal, Chats, Wochen'.($this->option('dry-run') ? ' (Probelauf)' : ''));

        $import = new BegleitungImport($tenant, new WordPressSource, (bool) $this->option('dry-run'));
        $stats = $import->run(fn (string $line) => $this->line('  '.$line, verbosity: 'v'));

        $this->table(['Termine', 'Teilnahmen', 'Material', 'Zuordnungen', 'Aufgaben', 'Notizen', 'Reflexionen', 'Journal', 'Kommentare', 'Gespraeche', 'Nachrichten', 'Wochen', 'Positionen'], [[
            $stats['termine'], $stats['teilnahmen'], $stats['material'], $stats['zuordnungen'], $stats['aufgaben'], $stats['notizen'], $stats['reflexionen'], $stats['journal'], $stats['kommentare'], $stats['gespraeche'], $stats['nachrichten'], $stats['wochen'], $stats['positionen'],
        ]]);
        foreach ($stats['hinweise'] as $h) {
            $this->warn('  '.$h);
        }
    }

    protected function inhalte(Tenant $tenant): void
    {
        $this->info('Impulse, Podcast, Themen, Merklisten'.($this->option('dry-run') ? ' (Probelauf)' : ''));

        $import = new InhalteImport($tenant, new WordPressSource, (bool) $this->option('dry-run'));
        $stats = $import->run(fn (string $line) => $this->line('  '.$line, verbosity: 'v'));

        $this->table(['Beitraege', 'Folgen', 'Themen', 'Zuordnungen', 'Profile', 'Merker'], [[
            $stats['beitraege'], $stats['folgen'], $stats['themen'], $stats['zuordnungen'], $stats['profile'], $stats['merker'],
        ]]);
        foreach ($stats['hinweise'] as $h) {
            $this->warn('  '.$h);
        }
    }
}

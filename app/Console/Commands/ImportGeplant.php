<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Geplanter Import aus WordPress waehrend des Parallelbetriebs (stuendlich im Scheduler).
 * Welche Teile je Mandant laufen, steht in tenants.settings.import.wordpress.schedule,
 * z. B. ['inhalte']. Ohne Eintrag passiert nichts. Der Import ist wiederholbar.
 */
class ImportGeplant extends Command
{
    protected $signature = 'import:geplant';

    protected $description = 'Laeuft die in den Einstellungen geplanten WordPress-Importe fuer alle Mandanten';

    public function handle(): int
    {
        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            $parts = array_values(array_intersect((array) $tenant->setting('import.wordpress.schedule', []), ['users', 'programs', 'begleitung', 'inhalte']));
            if ($parts === []) {
                continue;
            }
            $this->line("{$tenant->slug}: ".implode(', ', $parts));
            Artisan::call('import:wordpress', ['tenant' => $tenant->slug, '--only' => implode(',', $parts)], $this->output);
        }

        return self::SUCCESS;
    }
}

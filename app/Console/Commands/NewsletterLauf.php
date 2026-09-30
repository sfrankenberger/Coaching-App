<?php

namespace App\Console\Commands;

use App\Newsletter\Serien;
use App\Newsletter\Versand;
use App\Notifications\Runden;
use Illuminate\Console\Command;

/**
 * Newsletter in Wellen verschicken (jede Minute) und faellige Serienschritte (stuendlich), alle Mandanten.
 *   php84 artisan newsletter:lauf wellen
 *   php84 artisan newsletter:lauf serien
 */
class NewsletterLauf extends Command
{
    protected $signature = 'newsletter:lauf {was=wellen : wellen | serien} {--max=60}';

    protected $description = 'Newsletter-Wellen und Serienschritte fuer alle Mandanten';

    public function handle(Runden $runden, Versand $versand, Serien $serien): int
    {
        $was = $this->argument('was');
        $result = $runden->jeMandant(fn () => match ($was) {
            'wellen' => $versand->welle((int) $this->option('max')),
            'serien' => $serien->lauf(),
            default => throw new \InvalidArgumentException("Unbekannt: {$was}"),
        });
        foreach ($result as $slug => $n) {
            if ($n) {
                $this->line("{$slug}: {$n}");
            }
        }

        return self::SUCCESS;
    }
}

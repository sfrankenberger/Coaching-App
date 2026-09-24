<?php

namespace App\Console\Commands;

use App\Notifications\Runden;
use Illuminate\Console\Command;

/**
 * Wiederkehrende Benachrichtigungen ueber alle Mandanten.
 *   php84 artisan benachrichtigungen:runde termine     (alle 10 Minuten)
 *   php84 artisan benachrichtigungen:runde nachfassen  (alle 10 Minuten)
 *   php84 artisan benachrichtigungen:runde aufgaben --wann=morgen|abend
 *   php84 artisan benachrichtigungen:runde abendmail   (taeglich 19:30)
 */
class BenachrichtigungenRunde extends Command
{
    protected $signature = 'benachrichtigungen:runde {was : termine | nachfassen | aufgaben | abendmail} {--wann=morgen}';

    protected $description = 'Termin-Erinnerungen, Nachfassen, Aufgaben-Hinweise und Abendmail fuer alle Mandanten';

    public function handle(Runden $runden): int
    {
        $was = $this->argument('was');
        $result = $runden->jeMandant(fn () => match ($was) {
            'termine' => $runden->terminErinnerungen(),
            'nachfassen' => $runden->nachfassen(),
            'aufgaben' => $runden->aufgabenHinweis((string) $this->option('wann')),
            'abendmail' => $runden->abendmail(),
            default => throw new \InvalidArgumentException("Unbekannt: {$was}"),
        });

        foreach ($result as $slug => $n) {
            $this->line("{$slug}: {$n}");
        }

        return self::SUCCESS;
    }
}

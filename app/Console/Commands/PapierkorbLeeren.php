<?php

namespace App\Console\Commands;

use App\Support\Papierkorb\Papierkorb;
use Illuminate\Console\Command;

class PapierkorbLeeren extends Command
{
    protected $signature = 'papierkorb:leeren {--tage=90 : Aelter als so viele Tage} {--trocken : Nur zaehlen, nichts loeschen}';

    protected $description = 'Loescht endgueltig, was laenger als die Frist im Papierkorb liegt (alle Mandanten)';

    public function handle(Papierkorb $papierkorb): int
    {
        $tage = max(1, (int) $this->option('tage'));
        $anzahl = $papierkorb->leeren($tage, (bool) $this->option('trocken'));
        $this->info(($this->option('trocken') ? 'Wuerde loeschen: ' : 'Endgueltig geloescht: ').$anzahl.' Eintraege aelter als '.$tage.' Tage.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Support\Database;

use Illuminate\Support\Carbon;

/**
 * Zeitpunkte in Abfragen immer in UTC binden. Modelle liefern Zeitpunkte in Ortszeit
 * (siehe Ortszeit), die Datenbank speichert UTC. Ohne diese Umrechnung wuerde
 * where('starts_at', '>', $termin->starts_at) um die Zeitverschiebung danebenliegen.
 */
trait UtcBindings
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $bindings[$key] = Carbon::instance($value)->setTimezone(config('app.timezone'));
            }
        }

        return parent::prepareBindings($bindings);
    }
}

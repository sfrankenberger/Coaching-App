<?php

namespace App\Tenancy\Concerns;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

/**
 * Zeitpunkte liegen in der Datenbank in UTC (app.timezone), gelesen werden sie in der
 * Zeitzone des Mandanten. So zeigt jede Ansicht Ortszeit, ohne dass jede Stelle umrechnen muss.
 * Beim Schreiben wird wieder nach UTC umgerechnet. Reine Datumsspalten bleiben unberuehrt.
 */
trait Ortszeit
{
    public static function ortszone(): ?string
    {
        $tz = app(CurrentTenant::class)->get()?->timezone;

        return $tz && $tz !== config('app.timezone') ? $tz : null;
    }

    protected function asDateTime($value)
    {
        $datum = parent::asDateTime($value);
        $tz = static::ortszone();

        return $tz ? $datum->copy()->setTimezone($tz) : $datum;
    }

    protected function asDate($value)
    {
        return parent::asDateTime($value)->startOfDay();
    }

    /** Auch Zeitstempel ohne Cast (z. B. bei timestamps = false) landen immer als UTC im Modell. */
    public function setAttribute($key, $value)
    {
        if ($value instanceof \DateTimeInterface) {
            $value = Carbon::instance($value)->setTimezone(config('app.timezone'));
        }

        return parent::setAttribute($key, $value);
    }

    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }
        $datum = parent::asDateTime($value);

        return Carbon::instance($datum)->setTimezone(config('app.timezone'))->format($this->getDateFormat());
    }
}

<?php

namespace App\Support\Papierkorb;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Papierkorb: Loeschen ist ein Verschieben, 90 Tage lang laesst es sich zurueckholen.
 *
 * Am Modell einstellbar:
 *   protected static array $papierkorbKinder = ['steps'];      Beziehungen, die mit in den Papierkorb gehen und mit zurueckkommen
 *   protected static array $papierkorbEindeutig = ['slug'];     eindeutige Werte, die beim Loeschen frei werden (Suffix ~geloescht-…)
 *   protected static ?string $papierkorbEltern = 'program';     Elternteil: liegt es selbst im Papierkorb, zeigt die Liste nur das Elternteil
 */
trait ImPapierkorb
{
    use SoftDeletes;

    public const PAPIERKORB_SUFFIX = '~geloescht-';

    public static function bootImPapierkorb(): void
    {
        static::deleting(function (Model $m) {
            if ($m->isForceDeleting()) {
                foreach (static::papierkorbKinder() as $beziehung) {
                    $m->{$beziehung}()->withTrashed()->get()->each->forceDelete();
                }

                return;
            }

            // Eindeutige Werte freigeben, damit ein neuer Datensatz denselben Wert tragen darf
            $frei = [];
            foreach (static::papierkorbEindeutig() as $feld) {
                $wert = $m->getAttribute($feld);
                if (is_string($wert) && $wert !== '' && ! str_contains($wert, self::PAPIERKORB_SUFFIX)) {
                    $frei[$feld] = $wert.self::PAPIERKORB_SUFFIX.now()->format('YmdHis');
                }
            }
            if ($frei) {
                $m->newQueryWithoutScopes()->whereKey($m->getKey())->update($frei);
                $m->forceFill($frei)->syncOriginalAttributes(array_keys($frei));
            }

            foreach (static::papierkorbKinder() as $beziehung) {
                $m->{$beziehung}()->get()->each->delete();
            }
        });

        static::restoring(function (Model $m) {
            $seit = $m->getAttribute($m->getDeletedAtColumn());

            foreach (static::papierkorbEindeutig() as $feld) {
                $wert = $m->getAttribute($feld);
                if (! is_string($wert) || ! str_contains($wert, self::PAPIERKORB_SUFFIX)) {
                    continue;
                }
                $original = Str::before($wert, self::PAPIERKORB_SUFFIX);
                $belegt = $m->newQuery()->where($feld, $original)->whereKeyNot($m->getKey())->exists();
                $m->setAttribute($feld, $belegt ? $original.'-'.now()->format('YmdHis') : $original);
            }

            // Kinder, die mit dem Elternteil in den Papierkorb kamen, kommen mit zurueck
            if ($seit) {
                foreach (static::papierkorbKinder() as $beziehung) {
                    $m->{$beziehung}()->onlyTrashed()->where($m->getDeletedAtColumn(), '>=', $seit->copy()->subSeconds(2))->get()->each->restore();
                }
            }
        });
    }

    /** @return list<string> */
    public static function papierkorbKinder(): array
    {
        return static::$papierkorbKinder ?? [];
    }

    /** @return list<string> */
    public static function papierkorbEindeutig(): array
    {
        return static::$papierkorbEindeutig ?? [];
    }

    public static function papierkorbEltern(): ?string
    {
        return static::$papierkorbEltern ?? null;
    }

    /** Liegt das Elternteil selbst im Papierkorb? Dann zeigt die Liste nur das Elternteil. */
    public function papierkorbMitElternteil(): bool
    {
        $eltern = static::papierkorbEltern();
        if (! $eltern || ! method_exists($this, $eltern)) {
            return false;
        }
        $e = $this->{$eltern}()->withTrashed()->first();

        return $e && method_exists($e, 'trashed') && $e->trashed();
    }
}

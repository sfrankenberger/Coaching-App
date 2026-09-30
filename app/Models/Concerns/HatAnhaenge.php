<?php

namespace App\Models\Concerns;

use App\Models\Anhang;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Fuer alles, woran sich etwas anhaengen laesst (Notiz, Aufgabe, Reflexion, Frage). */
trait HatAnhaenge
{
    public static function bootHatAnhaenge(): void
    {
        // Im Papierkorb bleiben die Anhaenge dran, erst beim endgueltigen Loeschen gehen sie weg
        static::deleting(fn (self $m) => (! method_exists($m, 'isForceDeleting') || $m->isForceDeleting()) ? $m->anhaenge()->delete() : null);
    }

    public function anhaenge(): MorphMany
    {
        return $this->morphMany(Anhang::class, 'anhangAn', 'anhang_an_type', 'anhang_an_id')->orderBy('position');
    }

    /** Die Anhaenge als "art:nummer", so wie das Formular sie schickt. */
    public function anhangRefs(): array
    {
        return $this->anhaenge->map(fn (Anhang $a) => $a->ziel_type.':'.$a->ziel_id)->all();
    }
}

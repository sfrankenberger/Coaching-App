<?php

namespace App\Models\Concerns;

use App\Models\Anhang;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Fuer alles, woran sich etwas anhaengen laesst (Notiz, Aufgabe, Reflexion, Frage). */
trait HatAnhaenge
{
    public static function bootHatAnhaenge(): void
    {
        static::deleting(fn (self $m) => $m->anhaenge()->delete());
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

<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Support\Suche;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * Werkzeug fuer die Coach-Ausbildung: eine Methode mit Zweck, Einsatz, Ablauf und Beispiel.
 * Sehen es nur Personen mit dem Kennzeichen "Ausbildung" an der Mitgliedschaft und das Team.
 */
class Tool extends Model
{
    use BelongsToTenant, HasTopics, Searchable;

    public const FELDER = [
        'purpose' => ['Wofür ist es da', 'Ein bis zwei Sätze: welches Problem löst dieses Werkzeug?'],
        'fits_when' => ['Wann passt es', 'In welcher Situation setzt du es ein? Woran erkennst du den richtigen Moment?'],
        'not_when' => ['Wann passt es nicht', 'Wann würdest du es lassen? Gibt es Grenzen oder Vorsicht?'],
        'steps' => ['So geht es', 'Der Ablauf in Schritten, so dass eine Coachin es nachmachen kann.'],
        'example' => ['Beispiel aus der Praxis', 'Eine kurze Szene: Klientin, Situation, was passiert ist.'],
        'duration' => ['Dauer', 'Etwa wie lange dauert es? Zum Beispiel: 15 Minuten im Gespräch.'],
        'material' => ['Was es braucht', 'Material, Vorlagen, Raum. Leer lassen, wenn nichts nötig ist.'],
    ];

    protected $guarded = [];

    protected $attributes = ['is_published' => false, 'position' => 0];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Tool $t) {
            if (blank($t->slug)) {
                $t->slug = Str::slug($t->title) ?: 'werkzeug';
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'purpose' => Suche::text($this->purpose),
            'fits_when' => Suche::text($this->fits_when),
            'steps' => Suche::text($this->steps),
            'example' => Suche::text($this->example),
        ];
    }

    /** Darf diese Person Werkzeuge sehen? Team immer, sonst mit Kennzeichen an der Mitgliedschaft. */
    public static function darf(User $user): bool
    {
        return $user->canManageCurrentTenant() || (bool) $user->membershipIn()?->setting('ausbildung', false);
    }
}

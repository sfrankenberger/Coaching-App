<?php

namespace App\Support\Protokoll;

use App\Models\Protokoll;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\ActivityLogger;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Schreibt Anlegen, Aendern, Loeschen und Wiederherstellen ins Aenderungsprotokoll.
 *
 * Am Modell einstellbar:
 *   protected static array $protokollSensibel = ['body'];   nur "geaendert", nie der Inhalt
 *   protected static array $protokollIgnoriert = ['position']; loest keinen Eintrag aus
 *   public function protokollTitel(): string                 Titel im Verlauf
 *   public function protokollPerson(): ?int                  Coachee, um die es geht
 *   public function protokollPrivat(): bool                  true = Titel und Inhalt bleiben verborgen
 */
trait Protokolliert
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $ignoriert = array_values(array_unique(array_merge(config('protokoll.ignoriert', []), static::$protokollIgnoriert ?? [])));

        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept($ignoriert)
            ->dontLogIfAttributesChangedOnly($ignoriert)
            ->setDescriptionForEvent(fn (string $ereignis) => config('protokoll.ereignisse')[$ereignis] ?? $ereignis);
    }

    /** Ersetzt Spaties Boot: gleiche Ereignisse, dazu Mandant, Person, Titel und maskierte Felder. */
    protected static function bootLogsActivity(): void
    {
        static::eventsToBeRecorded()->each(function (string $ereignis) {
            if ($ereignis === 'updated') {
                static::updating(function (Model $model) {
                    $alt = (new static)->setRawAttributes($model->getRawOriginal());
                    $model->oldAttributes = static::extractChanges($alt);
                });
            }

            static::$ereignis(function (Model $model) use ($ereignis) {
                $model->activitylogOptions = $model->getActivitylogOptions();
                if (! $model->shouldLogEvent($ereignis)) {
                    return;
                }

                $privat = $model->protokollPrivat();
                $aenderungen = static::protokollMaskieren($model->buildChanges($ereignis), $privat);
                if ($model->shouldSkipEmptyLog($aenderungen)) {
                    return;
                }

                app(ActivityLogger::class)
                    ->useLog($model->getLogNameToUse())
                    ->event($ereignis)
                    ->performedOn($model)
                    ->withChanges($aenderungen)
                    ->withProperties(array_filter([
                        'titel' => $privat ? '(privater Eintrag)' : $model->protokollTitel(),
                        'quelle' => app()->runningInConsole() ? 'konsole' : 'web',
                        'verursacher' => auth()->user()?->name,
                    ], fn ($v) => $v !== null && $v !== ''))
                    ->tap(function (Protokoll $eintrag) use ($model) {
                        $eintrag->tenant_id = $model->getAttribute('tenant_id') ?? app(CurrentTenant::class)->id();
                        $eintrag->person_id = $model->protokollPerson();
                    })
                    ->log($ereignis === 'deleted' && method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                        ? 'endgültig gelöscht'
                        : $model->getDescriptionForEvent($ereignis));

                $model->activitylogOptions = null;
            });
        });
    }

    /** Titel im Verlauf: title, name oder Anfang des Textes. */
    public function protokollTitel(): string
    {
        foreach (['title', 'name', 'label', 'week_label', 'slug'] as $feld) {
            $wert = $this->getAttribute($feld);
            if (is_string($wert) && trim($wert) !== '') {
                return Str::limit(trim($wert), 80);
            }
        }
        if ($this->getAttribute('user_id') && method_exists($this, 'user')) {
            $name = $this->user()->withoutGlobalScopes()->value('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }
        foreach (['body', 'went_well', 'text'] as $feld) {
            $wert = $this->getAttribute($feld);
            if (is_string($wert) && trim($wert) !== '') {
                return Str::limit(trim(preg_replace('/\s+/', ' ', $wert)), 60);
            }
        }

        return '#'.$this->getKey();
    }

    /** Coachee, um die es geht: user_id am Datensatz, bei Personen die Person selbst. */
    public function protokollPerson(): ?int
    {
        if ($this instanceof User) {
            return $this->getKey();
        }
        $id = $this->getAttribute('user_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Private Eintraege (visibility = private) gehoeren nur der Person: das Team sieht im Verlauf
     * weder Titel noch Inhalt, nur dass sich etwas getan hat.
     */
    public function protokollPrivat(): bool
    {
        return $this->getAttribute('visibility') === 'private';
    }

    /** Sensible Felder: nur festhalten, dass sie sich geaendert haben. Bei privaten Eintraegen alle Felder. */
    protected static function protokollMaskieren(array $aenderungen, bool $alles = false): array
    {
        $sensibel = array_merge(config('protokoll.sensibel', []), static::$protokollSensibel ?? []);
        foreach (['attributes', 'old'] as $teil) {
            foreach ($aenderungen[$teil] ?? [] as $feld => $wert) {
                if ($wert !== null && ($alles || in_array($feld, $sensibel, true))) {
                    $aenderungen[$teil][$feld] = Protokoll::MASKE;
                }
            }
        }

        return $aenderungen;
    }
}

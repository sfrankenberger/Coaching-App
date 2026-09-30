<?php

namespace App\Models;

use App\Models\Concerns\HatAnhaenge;
use App\Support\Papierkorb\ImPapierkorb;
use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Aufgabe einer Person: selbst angelegt, von der Coachin gegeben, aus dem
 * Programm, aus einer Zusammenfassung oder aus einer Uebung.
 */
class Task extends Model
{
    use BelongsToTenant;
    use HatAnhaenge;
    use ImPapierkorb;
    use Protokolliert;

    public const SOURCES = ['manual' => 'Selbst', 'coach' => 'Von der Coachin', 'program' => 'Aus dem Kurs', 'ai_summary' => 'Aus der Zusammenfassung', 'exercise' => 'Aus einer Übung'];

    /** Art der Aufgabe => [Name, Symbol, Knopftext]. Der Knopf fuehrt dorthin, wo die Aufgabe erledigt wird. */
    public const KINDS = [
        'haken' => ['Abhaken', 'circle-check', ''],
        'notiz' => ['Notiz schreiben', 'feather', 'Notiz schreiben'],
        'reflexion' => ['Reflexion schreiben', 'pen-to-square', 'Reflexion schreiben'],
        'frage' => ['Frage stellen', 'comments', 'Frage stellen'],
        'aufzeichnung' => ['Aufzeichnung ansehen', 'circle-play', 'Ansehen'],
        'termin' => ['Termin buchen', 'calendar-plus', 'Termin buchen'],
    ];

    public const WEEKDAYS = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

    protected $guarded = [];

    protected $attributes = ['source' => 'manual', 'kind' => 'haken', 'visibility' => 'private', 'is_daily' => false, 'is_pinned' => false];

    protected static function booted(): void
    {
        // Wochentag plus Woche des Kurses ergibt die Faelligkeit (der Wochentag geht vor einem Datum von Hand)
        static::saving(function (Task $t) {
            if ($t->weekday && ($f = $t->faelligAusWoche())) {
                $t->due_at = $f;
            }
        });
    }

    /** Faelligkeit aus Woche (unlocks_at des Schritts) und Wochentag, wenn beides da ist. */
    public function faelligAusWoche(): ?Carbon
    {
        $start = $this->step_id ? ($this->relationLoaded('step') ? $this->step : $this->step()->first())?->unlocks_at : null;
        if (! $this->weekday || ! $start) {
            return null;
        }

        return $start->copy()->startOfWeek()->addDays($this->weekday - 1)->startOfDay();
    }

    public function kindLabel(): string
    {
        return (self::KINDS[$this->kind] ?? self::KINDS['haken'])[0];
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'is_daily' => 'boolean',
            'done_at' => 'datetime',
            'is_pinned' => 'boolean',
            'reminded_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProgramStep::class, 'step_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('done_at');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_at && $this->due_at->endOfDay()->isPast();
    }

    /** Bei taeglichen Aufgaben: welche Wochentage diese Woche abgehakt sind. */
    public function daysDone(): array
    {
        $week = now()->format('o-W');

        return $this->settings['days'][$week] ?? [];
    }
}

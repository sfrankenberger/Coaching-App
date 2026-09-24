<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Aufgabe einer Person: selbst angelegt, von der Coachin gegeben, aus dem
 * Programm, aus einer Zusammenfassung oder aus einer Uebung.
 */
class Task extends Model
{
    use BelongsToTenant;

    public const SOURCES = ['manual' => 'Selbst', 'coach' => 'Von der Coachin', 'program' => 'Aus dem Kurs', 'ai_summary' => 'Aus der Zusammenfassung', 'exercise' => 'Aus einer Übung'];

    protected $guarded = [];

    protected $attributes = ['source' => 'manual', 'visibility' => 'private', 'is_daily' => false, 'is_pinned' => false];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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

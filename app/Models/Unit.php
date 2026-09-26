<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Support\Suche;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

/**
 * Einheit: eine Lektion (Video, Text), eine Uebung (Fragen) oder ein Text.
 */
class Unit extends Model
{
    use BelongsToTenant, HasTopics, Searchable;

    public const TYPES = [
        'lesson' => 'Lektion',
        'exercise_set' => 'Übung',
        'text' => 'Text',
    ];

    protected $guarded = [];

    protected $attributes = ['type' => 'lesson', 'is_published' => true, 'is_core' => false, 'position' => 0];

    protected function casts(): array
    {
        return [
            'videos' => 'array',
            'links' => 'array',
            'is_published' => 'boolean',
            'is_core' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProgramStep::class, 'step_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('position');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /** Videos ohne leere Eintraege. */
    public function videoList(): array
    {
        return array_values(array_filter((array) $this->videos, fn ($v) => filled($v['url'] ?? null)));
    }

    /** Nur die Uebungsteile, auf die geantwortet werden kann. */
    public function answerableExercises(): Collection
    {
        return $this->exercises->filter(fn (Exercise $e) => $e->isAnswerable());
    }

    /** Suche (Scout, Datenbank): Felder als reiner Text. */
    public function toSearchableArray(): array
    {
        return [
            'title' => Suche::text($this->title),
            'intro' => Suche::text($this->intro),
            'body' => Suche::text($this->body),
        ];
    }
}

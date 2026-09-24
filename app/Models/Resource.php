<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Material: PDF, Audio, Video, Link, Text oder Bild. Haengt polymorph an
 * Programm, Schritt, Einheit, Termin oder direkt an einer Person (geteilt).
 */
class Resource extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'pdf' => 'PDF',
        'audio' => 'Audio',
        'video' => 'Video',
        'podcast' => 'Podcast',
        'link' => 'Link',
        'text' => 'Text',
        'image' => 'Bild',
    ];

    protected $guarded = [];

    protected $attributes = ['type' => 'pdf', 'is_archived' => false];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean', 'settings' => 'array'];
    }

    public function links(): HasMany
    {
        return $this->hasMany(Resourceable::class);
    }

    public function programs(): MorphToMany
    {
        return $this->morphedByMany(Program::class, 'resourceable');
    }

    public function units(): MorphToMany
    {
        return $this->morphedByMany(Unit::class, 'resourceable');
    }

    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'resourceable');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'resourceable')->withPivot('shared_by');
    }

    /** Ziel zum Oeffnen: Datei (signierte Route) oder externe Adresse. */
    public function target(): ?string
    {
        if ($this->file_path) {
            return route('material.datei', $this);
        }

        return $this->url;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}

<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Support\Suche;
use App\Support\Video;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;

/**
 * Material: PDF, Audio, Video, Link, Text oder Bild. Haengt polymorph an
 * Programm, Schritt, Einheit, Termin oder direkt an einer Person (geteilt).
 */
class Resource extends Model
{
    use BelongsToTenant, HasTopics, Searchable;

    public const TYPES = [
        'pdf' => 'PDF',
        'audio' => 'Audio',
        'video' => 'Video',
        'podcast' => 'Podcast',
        'link' => 'Link',
        'text' => 'Text',
        'image' => 'Bild',
    ];

    /** Stand der Videoaufbereitung (Vimeo). */
    public const PREPARE_STATUS = [
        'wartet' => 'Wartet auf die Textspur',
        'bereit' => 'Abschrift und Zusammenfassung da',
        'ohne_abschrift' => 'Ohne Abschrift (Vimeo liefert keine Textspur)',
        'fehler' => 'Aufbereitung nicht möglich',
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

    /** Ziel zum Oeffnen: Seite in der App (settings.ziel), Datei (signierte Route) oder externe Adresse. */
    public function target(): ?string
    {
        if ($ziel = $this->zielInApp()) {
            return url($ziel);
        }
        if ($this->file_path) {
            return route('material.datei', $this);
        }

        return $this->url;
    }

    /** Pfad in der App, auf den dieses Material zeigt (z. B. ein Arbeitsbuch, das als Kurs in der App liegt). */
    public function zielInApp(): ?string
    {
        $ziel = trim((string) ($this->settings['ziel'] ?? ''));

        return $ziel !== '' && str_starts_with($ziel, '/') ? $ziel : null;
    }

    /** Von Hand in der App gepflegt: der WordPress-Import laesst Titel, Art, Adresse und Text in Ruhe. */
    public function istEigen(): bool
    {
        return (bool) ($this->settings['eigen'] ?? false);
    }

    /** Laeuft in der App selbst: Video oder Audio mit eigener Seite, Text mit Inhalt. */
    public function hatSeite(): bool
    {
        return filled($this->summary)
            || ($this->type === 'video' && Video::embed($this->url))
            || (in_array($this->type, ['audio', 'podcast'], true) && $this->target())
            || ($this->type === 'text' && filled(trim(strip_tags((string) $this->body))));
    }

    /** Oeffnet in der App (eigene Seite oder Ziel in der App), sonst in einem neuen Fenster. */
    public function inApp(): bool
    {
        return $this->hatSeite() || $this->zielInApp() !== null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Suche (Scout, Datenbank): Felder als reiner Text. */
    public function toSearchableArray(): array
    {
        return [
            'title' => Suche::text($this->title),
            'description' => Suche::text($this->description),
            'body' => Suche::text($this->body),
            'summary' => Suche::text($this->summary),
            'transcript' => Suche::text($this->transcript),
        ];
    }
}

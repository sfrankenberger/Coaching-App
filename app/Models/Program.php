<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Ein Programm: Kurs, Club, Hybrid-Coaching, 1:1-Begleitung oder Workbook.
 * Unterschied nur in der Taktung (pacing) und der Art (type).
 */
class Program extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'hybrid' => 'Hybrid-Coaching (Gruppe, Wochen)',
        'selfpaced' => 'Selbstlernkurs',
        'one_on_one' => '1:1-Begleitung',
        'workbook' => 'Arbeitsbuch',
        'club' => 'Club',
    ];

    public const PACINGS = [
        'weekly' => 'Wöchentlich freischalten',
        'all' => 'Alles offen',
        'none' => 'Keine Schritte (1:1)',
    ];

    protected $guarded = [];

    protected $attributes = ['type' => 'selfpaced', 'pacing' => 'all', 'is_published' => true, 'is_internal' => false, 'position' => 0];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_published' => 'boolean',
            'is_internal' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProgramStep::class)->orderBy('position');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('position');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProgramMember::class);
    }

    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'offer_program');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isWorkbook(): bool
    {
        return $this->type === 'workbook';
    }

    public function isGroup(): bool
    {
        return in_array($this->type, ['hybrid', 'selfpaced', 'club', 'workbook'], true);
    }

    /** Einheiten in Reihenfolge der Schritte, dann der Position (fuer weiter/zurueck). */
    public function orderedUnits(): Collection
    {
        $stepOrder = $this->steps->pluck('position', 'id');

        return $this->units->filter(fn (Unit $u) => $u->is_published)->sortBy(fn (Unit $u) => sprintf('%08d-%08d', $stepOrder[$u->step_id] ?? 0, $u->position))->values();
    }
}

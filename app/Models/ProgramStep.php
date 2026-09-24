<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Schritt eines Programms: bei Wochentaktung eine Woche, sonst ein Modul.
 */
class ProgramStep extends Model
{
    use BelongsToTenant, HasTopics;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unlocks_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'step_id')->orderBy('position');
    }

    /** Freigeschaltet, wenn keine Taktung oder Zeitpunkt erreicht. */
    public function isUnlocked(?Program $program = null): bool
    {
        $program ??= $this->program;

        if ($program->pacing !== 'weekly' || $this->unlocks_at === null) {
            return true;
        }

        return $this->unlocks_at->isPast();
    }
}

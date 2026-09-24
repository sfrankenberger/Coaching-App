<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Notiz einer Person, optional an etwas gehaengt (Einheit, Termin ...).
 * Sichtbarkeit: private | coach | program | all.
 */
class Note extends Model
{
    use BelongsToTenant;

    public const VISIBILITIES = [
        'private' => 'Nur ich',
        'coach' => 'Mit der Coachin geteilt',
        'program' => 'Im Kurs sichtbar',
        'all' => 'In der Community',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

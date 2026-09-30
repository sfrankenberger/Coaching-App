<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Person in einem Programm (direkt zugeordnet, unabhaengig von Angeboten).
 */
class ProgramMember extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Der Zugang, aus dem diese Mitgliedschaft kommt (null: von Hand oder Import, gilt ohne Ablauf). */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }

    /** Gilt die Mitgliedschaft noch? Ohne Zugang immer, mit Zugang nur solange er laeuft. */
    public function gilt(): bool
    {
        return $this->entitlement_id === null || ($this->entitlement?->isCurrent() ?? false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

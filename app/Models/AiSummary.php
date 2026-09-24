<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** KI-Zusammenfassung zu einem Termin (Aufzeichnung) oder einer Podcastfolge, mit Aufgabenvorschlaegen. */
class AiSummary extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['kind' => 'summary', 'status' => 'pending'];

    protected function casts(): array
    {
        return ['tasks' => 'array'];
    }

    public function summarizable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}

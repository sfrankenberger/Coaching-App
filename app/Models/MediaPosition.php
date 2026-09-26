<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaPosition extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['seconds' => 'integer', 'duration' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Anteil gesehen, 0 bis 100. */
    public function prozent(): int
    {
        return $this->duration ? (int) min(100, round($this->seconds / $this->duration * 100)) : 0;
    }
}

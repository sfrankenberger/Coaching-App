<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antwort einer Person auf einen Uebungsteil. Privat, bis sie geteilt wird.
 */
class Answer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'shared_with_coach' => 'boolean',
            'shared_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Antwort als Text, fuer Anzeige und Suche. */
    public function asText(): string
    {
        $v = $this->value['v'] ?? null;

        return is_array($v) ? implode(', ', $v) : (string) $v;
    }

    public function isFilled(): bool
    {
        $v = $this->value['v'] ?? null;

        return is_array($v) ? $v !== [] : trim((string) $v) !== '';
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }
}

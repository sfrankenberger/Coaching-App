<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Wochenreflexion: drei Fragen (geklappt, schwer, Fokus), privat bis geteilt.
 */
class Reflection extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['visibility' => 'private'];

    protected function casts(): array
    {
        return ['shared_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function isShared(): bool
    {
        return $this->visibility !== 'private';
    }

    public function isEmpty(): bool
    {
        return blank($this->went_well) && blank($this->challenges) && blank($this->focus);
    }
}

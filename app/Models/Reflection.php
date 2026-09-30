<?php

namespace App\Models;

use App\Models\Concerns\HatAnhaenge;
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
    use HatAnhaenge;

    protected $guarded = [];

    protected $attributes = ['visibility' => 'private'];

    protected function casts(): array
    {
        return ['shared_at' => 'datetime'];
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProgramStep::class, 'step_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
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

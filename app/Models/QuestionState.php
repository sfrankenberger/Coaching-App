<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Je Person und Frage: folgen, stumm, zuletzt gelesen. */
class QuestionState extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['folgen' => 'boolean', 'seen_at' => 'datetime'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

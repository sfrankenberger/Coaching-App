<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Zuordnung Thema zu Inhalt (Pivot mit tenant_id). */
class Taggable extends MorphPivot
{
    use BelongsToTenant;

    protected $table = 'taggables';

    public $incrementing = true;

    protected $guarded = [];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }
}

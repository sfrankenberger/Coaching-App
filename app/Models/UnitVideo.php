<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Abschrift, Zusammenfassung und Kapitel zu einem Vimeo-Video einer Einheit. */
class UnitVideo extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function summaries(): MorphMany
    {
        return $this->morphMany(AiSummary::class, 'summarizable');
    }
}

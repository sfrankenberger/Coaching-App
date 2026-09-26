<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Zuordnung Material zu Programm, Schritt, Einheit, Termin oder Person. */
class Resourceable extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }
}

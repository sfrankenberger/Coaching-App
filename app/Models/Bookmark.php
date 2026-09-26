<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Merkliste: eine Person merkt sich Material, Termine, Einheiten, Beitraege. */
class Bookmark extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }
}

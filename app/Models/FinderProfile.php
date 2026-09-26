<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Themenfinder-Text zu einem Inhalt: worum geht es, wobei hilft es, Stichworte. */
class FinderProfile extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_checked' => 'boolean', 'generated_at' => 'datetime'];
    }

    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }
}

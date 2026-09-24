<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Zugehoerigkeit einer Person zu einem Mandanten, mit Rolle.
 * Eine Person (users) kann in mehreren Mandanten sein.
 */
class Membership extends Pivot
{
    protected $table = 'memberships';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'joined_at' => 'datetime',
        ];
    }
}

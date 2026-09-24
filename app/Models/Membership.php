<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Zugehoerigkeit einer Person zu einem Mandanten, mit Rolle.
 * Eine Person (users) kann in mehreren Mandanten sein.
 *
 * Als Pivot ueber tenant->users() bzw. user->tenants() erreichbar, als eigenes
 * Modell (Coach-Bereich, Import) ueber den Mandanten-Scope gefiltert.
 */
class Membership extends Pivot
{
    use BelongsToTenant;

    protected $table = 'memberships';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'joined_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Einzelne Einstellung, z. B. setting('notifications.abendmail', true). */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public static function statusLabels(): array
    {
        return ['active' => 'Aktiv', 'paused' => 'Pausiert', 'ended' => 'Beendet'];
    }
}

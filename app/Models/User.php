<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Person, plattformweit eindeutig ueber die Mailadresse.
 * Rollen haengen NICHT hier, sondern an der Mitgliedschaft je Mandant.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'avatar_path', 'is_platform_admin'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'memberships')
            ->using(Membership::class)
            ->withPivot(['role', 'status', 'legacy_id', 'joined_at'])
            ->withTimestamps();
    }

    /** Rolle im aktuellen (oder angegebenen) Mandanten, null = kein Zugang. */
    public function roleIn(?Tenant $tenant = null): ?Role
    {
        $tenant ??= app(CurrentTenant::class)->get();
        if (! $tenant) {
            return null;
        }

        $role = $this->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivot('status', 'active')
            ->first()?->pivot?->role;

        return $role instanceof Role ? $role : ($role ? Role::from($role) : null);
    }

    public function canManageCurrentTenant(): bool
    {
        return $this->is_platform_admin || (bool) $this->roleIn()?->canManage();
    }
}

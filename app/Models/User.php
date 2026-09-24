<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\CurrentTenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Person, plattformweit eindeutig ueber die Mailadresse.
 * Rollen haengen NICHT hier, sondern an der Mitgliedschaft je Mandant.
 */
class User extends Authenticatable implements FilamentUser
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
            ->withPivot(['role', 'status', 'legacy_id', 'joined_at', 'settings'])
            ->withTimestamps();
    }

    /** Mitgliedschaft im aktuellen (oder angegebenen) Mandanten, egal welcher Status. */
    public function membershipIn(?Tenant $tenant = null): ?Membership
    {
        $tenant ??= app(CurrentTenant::class)->get();
        if (! $tenant) {
            return null;
        }

        return Membership::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $this->id)
            ->first();
    }

    /** Rolle im aktuellen (oder angegebenen) Mandanten, null = kein aktiver Zugang. */
    public function roleIn(?Tenant $tenant = null): ?Role
    {
        $membership = $this->membershipIn($tenant);

        if (! $membership || ! $membership->isActive()) {
            return null;
        }

        return $membership->role;
    }

    public function hasAccessTo(?Tenant $tenant = null): bool
    {
        return $this->is_platform_admin || $this->roleIn($tenant) !== null;
    }

    public function canManageCurrentTenant(): bool
    {
        return $this->is_platform_admin || (bool) $this->roleIn()?->canManage();
    }

    /** Filament: coach nur fuer owner/team, plattform nur fuer Plattform-Admins. */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'coach' => $this->canManageCurrentTenant(),
            'plattform' => (bool) $this->is_platform_admin,
            default => false,
        };
    }

    public function vorname(): string
    {
        return Str::of($this->name)->trim()->before(' ')->toString() ?: $this->name;
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }
}

<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Mandant = eine Coachin bzw. ein Coaching-Business mit eigener App.
 */
class Tenant extends Model
{
    protected $fillable = ['slug', 'name', 'locale', 'timezone', 'currency', 'settings', 'branding', 'is_active'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'branding' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function primaryDomain(): ?string
    {
        return $this->domains()->where('is_primary', true)->value('domain')
            ?? $this->domains()->value('domain');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->using(Membership::class)
            ->withPivot(['role', 'status', 'legacy_id', 'joined_at'])
            ->withTimestamps();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', Role::Owner->value);
    }
}

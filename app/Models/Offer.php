<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Angebot: was man kaufen oder bekommen kann. Schaltet Programme frei.
 */
class Offer extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'course' => 'Einzelkurs',
        'club' => 'Club (Abo)',
        'hybrid' => 'Hybrid-Coaching',
        'one_on_one' => '1:1',
        'free' => 'Gratis-Einstieg',
    ];

    protected $guarded = [];

    protected $attributes = ['type' => 'course', 'is_free' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'offer_program');
    }

    public function products(): HasMany
    {
        return $this->hasMany(OfferProduct::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }
}

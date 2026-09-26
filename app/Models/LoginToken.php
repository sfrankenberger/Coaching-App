<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einmal-Link zum Anmelden (Magic Link). Gilt nur fuer den Mandanten,
 * auf dessen Domain er angefordert wurde.
 */
class LoginToken extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'token_hash', 'weiter', 'ip', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}

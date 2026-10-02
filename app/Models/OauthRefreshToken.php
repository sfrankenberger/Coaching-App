<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Erneuerungs-Token: holt ein frisches Zugangs-Token, ohne dass die Person sich neu anmeldet. */
class OauthRefreshToken extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OauthClient::class, 'oauth_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

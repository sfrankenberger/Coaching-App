<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Einmal-Code aus der Freigabe, wird am Token-Endpunkt gegen ein Token getauscht (PKCE S256). */
class OauthCode extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
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

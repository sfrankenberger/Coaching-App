<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verbindung einer Person zu Telegram (Bot je Mandant). */
class TelegramLink extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['active' => false];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_message_id_at' => 'datetime', 'settings' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

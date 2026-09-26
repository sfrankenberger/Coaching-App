<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Protokoll eingehender Webhooks (Shop), damit sich Zugaenge nachvollziehen lassen. */
class WebhookLog extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}

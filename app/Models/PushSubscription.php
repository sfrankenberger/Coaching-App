<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Web-Push-Abo eines Geraets, je Mandant (die Abos haengen am Ursprung der App). */
class PushSubscription extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

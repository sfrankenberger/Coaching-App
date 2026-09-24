<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Wer zu einem Termin gehoert, abgesagt hat, live dabei war oder die Aufzeichnung gesehen hat. */
class EventAttendee extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['status' => 'invited'];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

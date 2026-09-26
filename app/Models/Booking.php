<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine gebuchte Sitzung: Termin, Art, Antworten auf die Vorbereitungsfragen, Kalendereintrag. */
class Booking extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['answers' => 'array', 'starts_at' => 'datetime', 'block_ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BookingType::class, 'booking_type_id');
    }

    public function istAktiv(): bool
    {
        return $this->status === 'gebucht';
    }
}

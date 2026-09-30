<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Kontakt in einer Serie: welcher Schritt als naechstes kommt und wann. */
class SerienLauf extends Model
{
    use BelongsToTenant;

    protected $table = 'serien_laeufe';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['naechste_at' => 'datetime', 'fertig_at' => 'datetime'];
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function kontakt(): BelongsTo
    {
        return $this->belongsTo(Kontakt::class);
    }
}

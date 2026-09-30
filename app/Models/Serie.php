<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Serie (Autoresponder): ein Tag loest sie aus, die Schritte gehen mit Abstand in Tagen raus. */
class Serie extends Model
{
    use BelongsToTenant;

    protected $table = 'serien';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['schritte' => 'array', 'settings' => 'array', 'aktiv' => 'boolean'];
    }

    public function laeufe(): HasMany
    {
        return $this->hasMany(SerienLauf::class);
    }

    public function schritt(int $i): ?array
    {
        $s = array_values($this->schritte ?? []);

        return $s[$i] ?? null;
    }
}

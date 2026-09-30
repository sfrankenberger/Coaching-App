<?php

namespace App\Models;

use App\Support\Papierkorb\ImPapierkorb;
use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Buchbare Art einer Sitzung (Erstgespraech, 1:1 ...), je Mandant. */
class BookingType extends Model
{
    use BelongsToTenant;
    use ImPapierkorb;
    use Protokolliert;

    protected static array $papierkorbEindeutig = ['key'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'is_active' => 'boolean', 'questions' => 'array'];
    }

    public function blockMinuten(): int
    {
        return max($this->duration, (int) ($this->block_minutes ?: $this->duration));
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}

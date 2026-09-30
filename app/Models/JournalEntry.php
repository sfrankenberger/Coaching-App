<?php

namespace App\Models;

use App\Support\Papierkorb\ImPapierkorb;
use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Freier Journaleintrag (aus dem alten Beitragstyp journal). */
class JournalEntry extends Model
{
    use BelongsToTenant;
    use ImPapierkorb;
    use Protokolliert;

    /** Inhalt bleibt privat, im Verlauf steht nur, dass sich etwas geaendert hat. */
    protected static array $protokollSensibel = ['body'];

    protected $guarded = [];

    protected $attributes = ['type' => 'entry', 'visibility' => 'private', 'is_daily' => false];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'is_daily' => 'boolean', 'done_at' => 'datetime', 'settings' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

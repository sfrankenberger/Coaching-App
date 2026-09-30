<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kommentar an einem Element (Aufgabe, Notiz, Reflexion). */
class Comment extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    /** Inhalt bleibt privat, im Verlauf steht nur, dass sich etwas geaendert hat. */
    protected static array $protokollSensibel = ['body'];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

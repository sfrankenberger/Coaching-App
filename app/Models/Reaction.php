<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Reaktion (Emoji) an einem Element oder einer Nachricht. */
class Reaction extends Model
{
    use BelongsToTenant;

    public const EMOJIS = [
        'ja' => ['👍', 'Sehe ich auch so'],
        'auchich' => ['🙋', 'Kenne ich auch'],
        'stark' => ['🙌', 'Stark'],
        'herz' => ['❤️', 'Das bewegt mich'],
    ];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}

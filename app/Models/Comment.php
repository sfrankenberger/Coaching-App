<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kommentar an einem Element (Aufgabe, Notiz, Reflexion) oder Antwort auf eine Frage. */
class Comment extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    /** Inhalt bleibt privat, im Verlauf steht nur, dass sich etwas geaendert hat. */
    protected static array $protokollSensibel = ['body'];

    /** So lange darf die Autorin ihre Antwort noch aendern. */
    public const BEARBEITEN_MINUTEN = 15;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_best' => 'boolean', 'edited_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /** Die Autorin darf 15 Minuten lang nachbessern, das Team jederzeit. */
    public function bearbeitbarFuer(User $user): bool
    {
        if ($user->canManageCurrentTenant()) {
            return true;
        }

        return $this->user_id === $user->id && $this->created_at->gt(now()->subMinutes(self::BEARBEITEN_MINUTEN));
    }
}

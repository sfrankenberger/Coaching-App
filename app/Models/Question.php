<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Frage an die Coachin im Kursraum. */
class Question extends Model
{
    use BelongsToTenant;

    public const STATUS = [
        'offen' => 'Offen',
        'call' => 'Kommt in den Call',
        'beantwortet' => 'Beantwortet',
        'besprochen' => 'Im Call besprochen',
        'zu' => 'Abgeschlossen',
    ];

    /** Reaktion, mit der man sich die Frage fuer den Call wuenscht. */
    public const CALLWUNSCH = 'call';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function answers(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function isOffen(): bool
    {
        return in_array($this->status, ['offen', 'call'], true);
    }

    /** Fragen, die eine Person sehen darf: im Kurs sichtbar, oder eigene, oder alles fuer Verwaltende. */
    public function scopeSichtbarFuer(Builder $q, User $user): Builder
    {
        if ($user->canManageCurrentTenant()) {
            return $q;
        }

        return $q->where(fn (Builder $w) => $w->where('visibility', 'program')->orWhere('user_id', $user->id));
    }
}

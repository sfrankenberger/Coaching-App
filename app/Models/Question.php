<?php

namespace App\Models;

use App\Models\Concerns\HatAnhaenge;
use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/** Frage an die Coachin im Kursraum. */
class Question extends Model
{
    use BelongsToTenant;
    use HatAnhaenge;
    use Protokolliert;

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

    /** Reaktionen an einer Frage: Sehe ich auch so, Die Frage habe ich auch, Das bewegt mich. */
    public const REAKTIONEN = ['ja', 'auchich', 'herz'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime', 'last_answer_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Alle Antworten, auch die auf Antworten. */
    public function answers(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');
    }

    /** Nur die erste Ebene, die Antworten darauf haengen als children dran. */
    public function antwortenBaum(string $sort = 'alt'): Collection
    {
        $alle = $this->relationLoaded('answers') ? $this->answers : $this->answers()->with(['user:id,name', 'reactions'])->get();
        $eltern = $alle->whereNull('parent_id');
        $kinder = $alle->whereNotNull('parent_id')->groupBy('parent_id');
        $eltern->each(fn (Comment $c) => $c->setRelation('children', $kinder->get($c->id, collect())->values()));

        $eltern = match ($sort) {
            'neu' => $eltern->sortByDesc('created_at'),
            'herz' => $eltern->sortByDesc(fn (Comment $c) => [$c->reactions->count(), $c->created_at->timestamp]),
            default => $eltern->sortBy('created_at'),
        };

        // Die beste Antwort steht immer oben
        return $eltern->sortByDesc('is_best')->values();
    }

    public function states(): HasMany
    {
        return $this->hasMany(QuestionState::class);
    }

    public function stateFor(User $user): ?QuestionState
    {
        return $this->states->firstWhere('user_id', $user->id);
    }

    public function istZu(): bool
    {
        return $this->status === 'zu';
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

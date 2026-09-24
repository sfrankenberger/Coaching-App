<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gespraech: 1:1 zwischen einer Person und der Coachin (mit Team), oder
 * Gruppe zu einem Programm.
 */
class Conversation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['type' => 'direct'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function isDirect(): bool
    {
        return $this->type === 'direct';
    }

    public function participant(User $user): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $user->id);
    }

    public function unreadCountFor(User $user): int
    {
        $since = $this->participant($user)?->last_read_at;

        return $this->messages()->where('user_id', '!=', $user->id)->when($since, fn ($q) => $q->where('created_at', '>', $since))->count();
    }
}

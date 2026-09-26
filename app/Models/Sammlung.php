<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Sammlung: die Coachin waehlt im Nachschlagen Inhalte aus, gibt ihnen einen Namen und einen Gruss
 * und schickt sie in die 1:1-Gespraeche oder teilt einen Link.
 */
class Sammlung extends Model
{
    use BelongsToTenant;

    protected $table = 'sammlungen';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'recipients' => 'array', 'seen' => 'array', 'sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Sammlung $s) {
            if (blank($s->key)) {
                $s->key = Str::random(20);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return route('nachschlagen.sammlung', ['sammlung' => $this->id, 'key' => $this->key]);
    }

    public function gesehenVon(User $user): void
    {
        $seen = array_map('intval', (array) $this->seen);
        if (! in_array($user->id, $seen, true)) {
            $seen[] = $user->id;
            $this->forceFill(['seen' => array_values($seen)])->save();
        }
    }
}

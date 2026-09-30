<?php

namespace App\Support;

use App\Models\MediaPosition;
use App\Models\User;
use Illuminate\Support\Collection;

/** Wo eine Person in Videos und Aufzeichnungen steht: einmal je Anfrage geladen, fuer Listen und Knoepfe. */
class Medienstand
{
    protected array $cache = [];

    public function alle(User $user): Collection
    {
        return $this->cache[$user->id] ??= MediaPosition::where('user_id', $user->id)->get()->keyBy('key');
    }

    public function fuer(User $user, string $key): ?MediaPosition
    {
        return $this->alle($user)->get($key);
    }

    /** Anteil gesehen (0 bis 100) oder null, wenn noch nichts gespeichert ist. */
    public function prozent(User $user, string $key): ?int
    {
        $p = $this->fuer($user, $key);

        return $p && $p->duration ? $p->prozent() : null;
    }

    public function angeschaut(User $user, string $key): bool
    {
        $p = $this->fuer($user, $key);

        return $p && ($p->watched_at || ($p->duration && $p->seconds >= $p->duration * 0.8));
    }

    public function vergessen(User $user): void
    {
        unset($this->cache[$user->id]);
    }
}

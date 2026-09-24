<?php

namespace App\Programs;

use App\Models\Event;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Was eine Person an Terminen und Material sieht: alles aus ihren Programmen,
 * dazu ihre 1:1-Termine und direkt mit ihr geteiltes Material.
 */
class Begleitung
{
    public function __construct(protected ProgramAccess $access) {}

    /** Termine der Person (Gruppentermine ihrer Programme + eigene 1:1). */
    public function eventsQuery(User $user): Builder
    {
        $programIds = $this->access->programIdsFor($user);
        $manages = $user->canManageCurrentTenant();

        return Event::query()
            ->where('is_published', true)
            ->where(function (Builder $q) use ($programIds, $user, $manages) {
                $q->where('user_id', $user->id);
                if ($manages) {
                    $q->orWhereNotNull('id');
                } else {
                    $q->orWhere(fn (Builder $q2) => $q2->whereNull('user_id')->whereIn('program_id', $programIds));
                }
            });
    }

    public function canViewEvent(User $user, Event $event): bool
    {
        return $this->eventsQuery($user)->whereKey($event->id)->exists();
    }

    /** Material der Person: an Programmen, Schritten, Einheiten, Terminen ihrer Programme, oder direkt geteilt. */
    public function resourcesQuery(User $user): Builder
    {
        $programIds = $this->access->programIdsFor($user);
        $manages = $user->canManageCurrentTenant();

        return Resource::query()
            ->where('is_archived', false)
            ->where(function (Builder $q) use ($programIds, $user, $manages) {
                $q->whereHas('links', function (Builder $l) use ($programIds, $user, $manages) {
                    $l->where(function (Builder $w) use ($programIds, $user, $manages) {
                        $w->where(fn ($x) => $x->where('resourceable_type', 'user')->where('resourceable_id', $user->id));
                        if ($manages) {
                            $w->orWhereNotNull('id');

                            return;
                        }
                        $w->orWhere(fn ($x) => $x->where('resourceable_type', 'program')->whereIn('resourceable_id', $programIds));
                        $w->orWhere(fn ($x) => $x->where('resourceable_type', 'step')->whereIn('resourceable_id', fn ($s) => $s->select('id')->from('program_steps')->whereIn('program_id', $programIds)));
                        $w->orWhere(fn ($x) => $x->where('resourceable_type', 'unit')->whereIn('resourceable_id', fn ($s) => $s->select('id')->from('units')->whereIn('program_id', $programIds)));
                        $w->orWhere(fn ($x) => $x->where('resourceable_type', 'event')->whereIn('resourceable_id', fn ($s) => $s->select('id')->from('events')->whereIn('program_id', $programIds)->where(fn ($e) => $e->whereNull('user_id')->orWhere('user_id', $user->id))));
                    });
                });
            });
    }

    public function canViewResource(User $user, Resource $resource): bool
    {
        return $this->resourcesQuery($user)->whereKey($resource->id)->exists();
    }

    /** Aufzeichnungen als Materialzeilen (Termine mit recording_url). */
    public function recordings(User $user): Collection
    {
        return $this->eventsQuery($user)->whereNotNull('recording_url')->orderByDesc('starts_at')->get();
    }
}

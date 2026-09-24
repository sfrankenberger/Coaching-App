<?php

namespace App\Content;

use App\Models\Bookmark;
use App\Models\Event;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Resource;
use App\Models\Taggable;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Was eine Person an Impulsen, Podcastfolgen, Themen und Gemerktem sieht,
 * und eine einheitliche Zeile fuer alle Inhaltsarten (Themenfinder, Merkliste).
 */
class Inhalte
{
    public const ARTEN = ['post' => 'Impuls', 'episode' => 'Podcast', 'unit' => 'Einheit', 'step' => 'Schritt', 'program' => 'Programm', 'resource' => 'Material', 'event' => 'Termin'];

    public function __construct(protected ProgramAccess $access, protected Begleitung $begleitung) {}

    public function postsQuery(User $user): Builder
    {
        // Verwaltende sehen alles, auch Geplantes und Abgeschaltetes (Vorschau)
        if ($user->canManageCurrentTenant()) {
            return Post::query();
        }
        $q = Post::query()->published();
        $programIds = $this->access->programIdsFor($user);

        return $q->where(fn (Builder $w) => $w->where('visibility', 'members')
            ->orWhere(fn (Builder $p) => $p->where('visibility', 'program')->whereIn('program_id', $programIds)));
    }

    public function canViewPost(User $user, Post $post): bool
    {
        return $this->postsQuery($user)->whereKey($post->id)->exists();
    }

    public function episodesQuery(User $user): Builder
    {
        return PodcastEpisode::query()->published();
    }

    /** Themen mit Anzahl sichtbarer Inhalte. */
    public function topicsFor(User $user): Collection
    {
        return Topic::where('is_visible', true)->whereHas('taggables')->withCount('taggables')->orderBy('position')->orderBy('name')->get();
    }

    /** Alle Inhalte zu einem Thema, die die Person sehen darf, als einheitliche Zeilen. */
    public function itemsForTopic(Topic $topic, User $user): Collection
    {
        $models = Taggable::where('topic_id', $topic->id)->with('taggable')->get()->map(fn (Taggable $t) => $t->taggable)->filter();

        return $this->rows($models, $user);
    }

    /** Merkliste der Person als Zeilen (nur, was sie noch sehen darf). */
    public function bookmarksFor(User $user): Collection
    {
        $models = Bookmark::where('user_id', $user->id)->latest()->with('bookmarkable')->get()->map(fn (Bookmark $b) => $b->bookmarkable)->filter();

        return $this->rows($models, $user, keepOrder: true);
    }

    public function bookmarkKeys(User $user): Collection
    {
        return Bookmark::where('user_id', $user->id)->get()->map(fn ($b) => $b->bookmarkable_type.'-'.$b->bookmarkable_id)->flip();
    }

    /** Modelle filtern (Zugriff) und in Zeilen verwandeln. */
    public function rows(Collection $models, User $user, bool $keepOrder = false): Collection
    {
        $manages = $user->canManageCurrentTenant();
        $programIds = $manages ? null : $this->access->programIdsFor($user);
        $postIds = null;
        $resourceIds = null;
        $eventIds = null;

        $rows = collect();
        foreach ($models as $m) {
            $ok = match (true) {
                $m instanceof Post => ($postIds ??= $this->postsQuery($user)->pluck('id'))->contains($m->id),
                $m instanceof PodcastEpisode => $m->is_published,
                $m instanceof Program => $manages || ($m->is_published && $programIds->contains($m->id)),
                $m instanceof ProgramStep => $manages || $programIds->contains($m->program_id),
                $m instanceof Unit => ($manages || ($m->is_published && $programIds->contains($m->program_id))),
                $m instanceof Resource => ($resourceIds ??= $this->begleitung->resourcesQuery($user)->pluck('id'))->contains($m->id),
                $m instanceof Event => ($eventIds ??= $this->begleitung->eventsQuery($user)->pluck('id'))->contains($m->id),
                default => false,
            };
            if ($ok) {
                $rows->push($this->row($m));
            }
        }

        return $keepOrder ? $rows->values() : $rows->sortByDesc('ts')->values();
    }

    /** Einheitliche Zeile: art, id, titel, text, url, bild, typ, ts, model. */
    public function row(Model $m): array
    {
        return match (true) {
            $m instanceof Post => ['art' => 'post', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->excerptText(), 'url' => route('impulse.show', $m), 'bild' => $m->image_url, 'typ' => $m->typeLabel(), 'ts' => $m->published_at ?? $m->created_at, 'model' => $m],
            $m instanceof PodcastEpisode => ['art' => 'episode', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->excerptText(), 'url' => route('impulse.folge', $m), 'bild' => $m->image_url, 'typ' => 'Podcast · '.$m->show, 'ts' => $m->published_at ?? $m->created_at, 'model' => $m],
            $m instanceof Program => ['art' => 'program', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->subtitle, 'url' => route('kurse.show', $m), 'bild' => null, 'typ' => 'Programm', 'ts' => $m->created_at, 'model' => $m],
            $m instanceof ProgramStep => ['art' => 'step', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->program?->title, 'url' => $m->program ? route('kurse.schritt', [$m->program, $m]) : null, 'bild' => null, 'typ' => 'Schritt', 'ts' => $m->created_at, 'model' => $m],
            $m instanceof Unit => ['art' => 'unit', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->program?->title, 'url' => $m->program ? route('kurse.einheit', [$m->program, $m]) : null, 'bild' => null, 'typ' => 'Einheit', 'ts' => $m->created_at, 'model' => $m],
            $m instanceof Resource => ['art' => 'resource', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->description, 'url' => $m->target(), 'bild' => $m->image_url, 'typ' => $m->typeLabel(), 'ts' => $m->created_at, 'model' => $m],
            $m instanceof Event => ['art' => 'event', 'id' => $m->id, 'titel' => $m->title, 'text' => $m->starts_at->translatedFormat('j. F Y'), 'url' => route('termine.show', $m), 'bild' => null, 'typ' => $m->typeLabel(), 'ts' => $m->starts_at, 'model' => $m],
            default => ['art' => 'x', 'id' => $m->getKey(), 'titel' => (string) ($m->title ?? ''), 'text' => null, 'url' => null, 'bild' => null, 'typ' => '', 'ts' => $m->created_at, 'model' => $m],
        };
    }
}

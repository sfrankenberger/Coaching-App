<?php

namespace App\Http\Controllers;

use App\Content\Inhalte;
use App\Models\Event;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Resource;
use App\Models\Unit;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use App\Support\Suche;
use App\Support\Zeit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eine Suche ueber alles, was die Person sehen darf: Lektionen, Material, Aufzeichnungen
 * (samt Abschrift und Zusammenfassung), Impulse und Podcastfolgen. Scout mit Datenbank-Treiber.
 */
class SucheController extends Controller
{
    public function __construct(protected ProgramAccess $access, protected Begleitung $begleitung, protected Inhalte $inhalte) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $gruppen = collect();

        if (mb_strlen($q) >= 2) {
            $programme = $this->access->programIdsFor($user);
            $manages = $user->canManageCurrentTenant();

            $gruppen = collect([
                ['titel' => 'Lektionen', 'icon' => 'graduation-cap', 'treffer' => Unit::search($q)
                    ->query(fn ($b) => $b->whereIn('program_id', $programme)->when(! $manages, fn ($w) => $w->where('is_published', true))->with('program:id,slug,title'))
                    ->take(20)->get()->filter(fn ($u) => $u->program)->map(fn ($u) => [
                        'titel' => $u->title, 'wo' => $u->program->title, 'text' => Suche::ausschnitt($u->intro.' '.$u->body, $q), 'url' => route('kurse.einheit', [$u->program, $u]),
                    ])],
                ['titel' => 'Material', 'icon' => 'folder-open', 'treffer' => Resource::search($q)
                    ->query(fn ($b) => $b->whereIn('id', $this->begleitung->resourcesQuery($user)->select('resources.id')))
                    ->take(20)->get()->map(fn ($r) => [
                        'titel' => $r->title, 'wo' => $r->typeLabel(), 'text' => Suche::ausschnitt($r->summary ?: $r->description.' '.$r->transcript, $q), 'url' => $r->hatSeite() ? route('material.show', $r) : ($r->target() ?: route('material.index')),
                    ])],
                ['titel' => 'Termine und Aufzeichnungen', 'icon' => 'circle-play', 'treffer' => Event::search($q)
                    ->query(fn ($b) => $b->whereIn('id', $this->begleitung->eventsQuery($user)->select('events.id')))
                    ->take(20)->get()->map(fn ($e) => [
                        'titel' => $e->title, 'wo' => Zeit::tag($e->starts_at), 'text' => Suche::ausschnitt($e->summary ?: $e->description.' '.$e->transcript, $q), 'url' => route('termine.show', $e),
                    ])],
                ['titel' => 'Impulse', 'icon' => 'lightbulb', 'treffer' => Post::search($q)
                    ->query(fn ($b) => $b->whereIn('id', $this->inhalte->postsQuery($user)->select('posts.id')))
                    ->take(20)->get()->map(fn ($p) => [
                        'titel' => $p->title, 'wo' => 'Impuls', 'text' => Suche::ausschnitt($p->excerpt ?: $p->body, $q), 'url' => route('impulse.show', $p),
                    ])],
                ['titel' => 'Podcast', 'icon' => 'microphone', 'treffer' => PodcastEpisode::search($q)
                    ->query(fn ($b) => $b->whereIn('id', $this->inhalte->episodesQuery($user)->select('podcast_episodes.id')))
                    ->take(20)->get()->map(fn ($e) => [
                        'titel' => $e->title, 'wo' => $e->show ?: 'Podcast', 'text' => Suche::ausschnitt($e->summary ?: $e->excerpt.' '.$e->transcript, $q), 'url' => route('impulse.folge', $e),
                    ])],
            ])->filter(fn ($g) => $g['treffer']->isNotEmpty());
        }

        return view('suche', ['q' => $q, 'gruppen' => $gruppen]);
    }
}

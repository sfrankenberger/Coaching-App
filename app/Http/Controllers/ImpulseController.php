<?php

namespace App\Http\Controllers;

use App\Content\Inhalte;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Support\Besuche;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Impulse (Beitraege) und Podcastfolgen. */
class ImpulseController extends Controller
{
    public function __construct(protected Inhalte $inhalte) {}

    public function index(Request $request): View
    {
        app(Besuche::class)->merken($request->user(), 'impulse');
        $user = $request->user();
        $filter = (string) $request->query('f', '');
        $suche = mb_strtolower(trim((string) $request->query('q', '')));

        $zeilen = collect();
        if ($filter === '' || $filter === 'impuls' || $filter === 'neuigkeit') {
            $posts = $this->inhalte->postsQuery($user)->when(in_array($filter, ['impuls', 'neuigkeit'], true), fn ($q) => $q->where('type', $filter))->orderByDesc('published_at')->limit(200)->get();
            // Verwaltende sehen auch Entwuerfe und Team-Beitraege, mit Hinweis, was die Personen nicht sehen
            $sichtbar = $user->canManageCurrentTenant() ? Post::query()->published()->where('visibility', '!=', 'team')->pluck('id')->flip() : null;
            $gelesen = $this->inhalte->gelesen($user);
            $seit = $user->membershipIn()?->joined_at ?? now()->subDays(14);
            $zeilen = $zeilen->merge($posts->map(fn (Post $p) => $this->inhalte->row($p) + ['neu' => ! $user->canManageCurrentTenant() && ! $gelesen->contains($p->id) && ($p->published_at ?? $p->created_at)->gt($seit)] + ['versteckt' => $sichtbar !== null && ! $sichtbar->has($p->id) ? ($p->visibility === 'team' ? 'Nur Team' : (! $p->is_published ? 'Ausgeschaltet' : 'Geplant')) : null]));
        }
        $shows = $this->inhalte->episodesQuery($user)->select('show')->distinct()->orderBy('show')->pluck('show');
        if ($filter === '' || str_starts_with($filter, 'podcast')) {
            $show = $filter === 'podcast' ? null : (str_starts_with($filter, 'podcast:') ? substr($filter, 8) : null);
            $episodes = $this->inhalte->episodesQuery($user)->when($show, fn ($q) => $q->where('show', $show))->orderByDesc('published_at')->limit(200)->get();
            $zeilen = $zeilen->merge($episodes->map(fn (PodcastEpisode $e) => $this->inhalte->row($e)));
        }
        if ($suche !== '') {
            $zeilen = $zeilen->filter(fn ($z) => str_contains(mb_strtolower($z['titel'].' '.$z['text']), $suche));
        }

        // Seitenweise, 24 je Seite: die Liste mit Bildkarten wird sonst sehr lang
        $alle = $zeilen->sortByDesc('ts')->values();
        $seite = max(1, (int) $request->query('seite', 1));
        $proSeite = 24;

        // Ungelesene Neuigkeiten fuer die Pille
        $ungelesen = $user->canManageCurrentTenant() ? 0 : $this->inhalte->postsQuery($user)->where('type', 'neuigkeit')->where('published_at', '>', $user->membershipIn()?->joined_at ?? now()->subDays(14))
            ->whereNotIn('id', $this->inhalte->gelesen($user)->all() ?: [0])->count();

        return view('impulse.index', [
            'ungelesen' => $ungelesen,
            'zeilen' => $alle->slice(0, $seite * $proSeite)->values(),
            'mehr' => $alle->count() > $seite * $proSeite ? $seite + 1 : null,
            'gesamt' => $alle->count(),
            'filter' => $filter,
            'suche' => $suche,
            'shows' => $shows,
            'gemerkt' => $this->inhalte->bookmarkKeys($user),
        ]);
    }

    public function show(Request $request, Post $post): View
    {
        abort_unless($this->inhalte->canViewPost($request->user(), $post), 404);
        $post->load('topics');
        $this->inhalte->gelesenMerken($request->user(), $post);

        return view('impulse.show', ['post' => $post, 'gemerkt' => $this->inhalte->bookmarkKeys($request->user())]);
    }

    public function folge(Request $request, PodcastEpisode $folge): View
    {
        abort_unless($folge->is_published || $request->user()->canManageCurrentTenant(), 404);
        $folge->load('topics');
        // Nachbarn in derselben Sendung und Verwandte ueber gemeinsame Themen (wie lea-podcast)
        $reihe = $this->inhalte->episodesQuery($request->user())->where('show', $folge->show);
        $wann = $folge->published_at ?? $folge->created_at;
        $vorher = (clone $reihe)->where('published_at', '<', $wann)->orderByDesc('published_at')->first();
        $nachher = (clone $reihe)->where('published_at', '>', $wann)->orderBy('published_at')->first();
        $themen = $folge->topics->pluck('id');
        $verwandt = $themen->isEmpty() ? collect() : $this->inhalte->rows(
            PodcastEpisode::query()->published()->whereKeyNot($folge->id)->whereHas('topics', fn ($q) => $q->whereIn('topics.id', $themen))->latest('published_at')->limit(3)->get()
                ->concat(Post::query()->published()->whereHas('topics', fn ($q) => $q->whereIn('topics.id', $themen))->latest('published_at')->limit(3)->get()),
            $request->user())->take(4);

        return view('impulse.folge', ['folge' => $folge, 'gemerkt' => $this->inhalte->bookmarkKeys($request->user()), 'vorher' => $vorher, 'nachher' => $nachher, 'verwandt' => $verwandt]);
    }
}

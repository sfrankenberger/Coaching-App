<?php

namespace App\Http\Controllers;

use App\Content\Inhalte;
use App\Models\PodcastEpisode;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Impulse (Beitraege) und Podcastfolgen. */
class ImpulseController extends Controller
{
    public function __construct(protected Inhalte $inhalte) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = (string) $request->query('f', '');
        $suche = mb_strtolower(trim((string) $request->query('q', '')));

        $zeilen = collect();
        if ($filter === '' || $filter === 'impuls' || $filter === 'neuigkeit') {
            $posts = $this->inhalte->postsQuery($user)->when(in_array($filter, ['impuls', 'neuigkeit'], true), fn ($q) => $q->where('type', $filter))->orderByDesc('published_at')->limit(200)->get();
            $zeilen = $zeilen->merge($posts->map(fn (Post $p) => $this->inhalte->row($p)));
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

        return view('impulse.index', [
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

        return view('impulse.show', ['post' => $post, 'gemerkt' => $this->inhalte->bookmarkKeys($request->user())]);
    }

    public function folge(Request $request, PodcastEpisode $folge): View
    {
        abort_unless($folge->is_published || $request->user()->canManageCurrentTenant(), 404);
        $folge->load('topics');

        return view('impulse.folge', ['folge' => $folge, 'gemerkt' => $this->inhalte->bookmarkKeys($request->user())]);
    }
}

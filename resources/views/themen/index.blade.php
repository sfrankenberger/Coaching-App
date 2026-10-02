<x-layouts.app title="Themen">
    <h1 class="mb-1">Themen</h1>
    <p class="unterzeile m-0 mb-3.5">Was beschäftigt dich gerade? Wähle ein Thema, dann findest du alles dazu: Lektionen, Impulse, Podcastfolgen, Material.</p>
    <form method="get" class="suche m-0 mb-4">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="{{ $suche }}" placeholder="Ein Wort wie Schneekugel, oder sag, was gerade los ist" aria-label="Thema suchen">
    </form>

    @if ($themen->isEmpty())
        <div class="leer"><i class="fa-regular fa-bookmark"></i>Noch keine Themen. Sie entstehen, sobald Inhalte zugeordnet sind.</div>
    @else
        {{-- Als Wolke, die grossen Themen zuerst: 50 Karten untereinander liest niemand --}}
        <div class="themen-wolke">
            @foreach ($themen->sortByDesc('taggables_count') as $t)
                <a href="{{ route('themen.show', $t) }}" class="pille" title="{{ $t->description }}">{{ $t->name }}<em>{{ $t->taggables_count }}</em></a>
            @endforeach
        </div>
    @endif
</x-layouts.app>

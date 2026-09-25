<x-layouts.app title="Themen">
    <h1 style="margin-bottom:4px">Themen</h1>
    <p class="unterzeile" style="margin:0 0 14px">Was beschäftigt dich gerade? Wähle ein Thema, dann findest du alles dazu: Lektionen, Impulse, Podcastfolgen, Material.</p>
    <form method="get" class="suche" style="margin:0 0 16px">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="{{ $suche }}" placeholder="Ein Wort wie Schneekugel, oder sag, was gerade los ist" aria-label="Thema suchen">
    </form>

    @if ($themen->isEmpty())
        <div class="leer"><i class="fa-regular fa-bookmark"></i>Noch keine Themen. Sie entstehen, sobald Inhalte zugeordnet sind.</div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach ($themen as $t)
                <a href="{{ route('themen.show', $t) }}" class="karte !mb-0 no-underline flex items-center justify-between gap-3">
                    <span>
                        <span class="t">{{ $t->name }}</span>
                        @if ($t->description)<span class="hinweis block">{{ \Illuminate\Support\Str::limit($t->description, 90) }}</span>@endif
                    </span>
                    <span class="chip shrink-0">{{ $t->taggables_count }}</span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>

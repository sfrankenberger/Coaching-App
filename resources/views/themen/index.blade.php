<x-layouts.app title="Themen">
    <h1 class="mb-1">Themen</h1>
    <p class="text-ink-soft mb-3">Was beschäftigt dich gerade? Wähle ein Thema, dann findest du alles dazu: Lektionen, Impulse, Podcastfolgen, Material.</p>
    <form method="get" class="mb-3">
        <input type="search" name="q" value="{{ $suche }}" class="feld" placeholder="Thema suchen">
    </form>

    @if ($themen->isEmpty())
        <x-karte><p class="text-ink-soft">Noch keine Themen. Sie entstehen, sobald Inhalte zugeordnet sind.</p></x-karte>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach ($themen as $t)
                <a href="{{ route('themen.show', $t) }}" class="karte !mt-0 no-underline text-ink hover:border-primary flex items-center justify-between gap-3">
                    <span>
                        <span class="block text-base">{{ $t->name }}</span>
                        @if ($t->description)<span class="hinweis block">{{ \Illuminate\Support\Str::limit($t->description, 90) }}</span>@endif
                    </span>
                    <span class="hinweis shrink-0">{{ $t->taggables_count }}</span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>

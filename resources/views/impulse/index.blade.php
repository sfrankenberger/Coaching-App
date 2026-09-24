<x-layouts.app title="Impulse">
    <h1 class="mb-3">Impulse</h1>

    <div class="flex flex-wrap gap-2 mb-2">
        <a href="{{ route('impulse.index') }}" @class(['knopf', 'knopf-leise' => $filter !== ''])" style="min-height:36px;padding:6px 14px">Alles</a>
        <a href="{{ route('impulse.index', ['f' => 'impuls']) }}" @class(['knopf', 'knopf-leise' => $filter !== 'impuls'])" style="min-height:36px;padding:6px 14px">Impulse</a>
        <a href="{{ route('impulse.index', ['f' => 'neuigkeit']) }}" @class(['knopf', 'knopf-leise' => $filter !== 'neuigkeit'])" style="min-height:36px;padding:6px 14px">Neuigkeiten</a>
        @if ($shows->count() === 1)
            <a href="{{ route('impulse.index', ['f' => 'podcast']) }}" @class(['knopf', 'knopf-leise' => $filter !== 'podcast'])" style="min-height:36px;padding:6px 14px">Podcast</a>
        @else
            @foreach ($shows as $s)
                <a href="{{ route('impulse.index', ['f' => 'podcast:'.$s]) }}" @class(['knopf', 'knopf-leise' => $filter !== 'podcast:'.$s])" style="min-height:36px;padding:6px 14px">{{ $s }}</a>
            @endforeach
        @endif
        <a href="{{ route('themen.index') }}" class="knopf knopf-leise" style="min-height:36px;padding:6px 14px">Nach Thema</a>
    </div>
    <form method="get" class="mb-3">
        @if ($filter) <input type="hidden" name="f" value="{{ $filter }}"> @endif
        <input type="search" name="q" value="{{ $suche }}" class="feld" placeholder="In Impulsen und Podcast suchen">
    </form>

    @forelse ($zeilen as $z)
        <x-inhalt-zeile :z="$z" :gemerkt="$gemerkt" />
    @empty
        <x-karte><p class="text-ink-soft">{{ $suche ? 'Nichts gefunden.' : 'Hier kommen Impulse und Podcastfolgen hin, sobald es welche gibt.' }}</p></x-karte>
    @endforelse
</x-layouts.app>

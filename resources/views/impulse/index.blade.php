<x-layouts.app title="Impulse">
    <h1>Impulse</h1>

    <div class="pillen">
        <a href="{{ route('impulse.index') }}" @class(['pille', 'an' => $filter === ''])>Alles</a>
        <a href="{{ route('impulse.index', ['f' => 'impuls']) }}" @class(['pille', 'an' => $filter === 'impuls'])>Impulse</a>
        <a href="{{ route('impulse.index', ['f' => 'neuigkeit']) }}" @class(['pille', 'an' => $filter === 'neuigkeit'])>Neuigkeiten</a>
        @if ($shows->count() === 1)
            <a href="{{ route('impulse.index', ['f' => 'podcast']) }}" @class(['pille', 'an' => $filter === 'podcast'])>Podcast</a>
        @else
            @foreach ($shows as $s)
                <a href="{{ route('impulse.index', ['f' => 'podcast:'.$s]) }}" @class(['pille', 'an' => $filter === 'podcast:'.$s])>{{ $s }}</a>
            @endforeach
        @endif
        <a href="{{ route('themen.index') }}" class="pille"><i class="fa-solid fa-magnifying-glass"></i>Nach Thema</a>
    </div>
    <form method="get" class="suche m-0 mb-4">
        <i class="fa-solid fa-magnifying-glass"></i>
        @if ($filter) <input type="hidden" name="f" value="{{ $filter }}"> @endif
        <input type="search" name="q" value="{{ $suche }}" placeholder="Impulse durchsuchen" aria-label="Impulse durchsuchen">
    </form>

    @forelse ($zeilen as $z)
        <x-inhalt-zeile :z="$z" :gemerkt="$gemerkt" />
        @if ($loop->last && $mehr)
            <a href="{{ route('impulse.index', array_filter(['f' => $filter, 'q' => $suche, 'seite' => $mehr])) }}#mehr-{{ $loop->iteration }}" id="mehr-{{ $loop->iteration }}" class="knopf knopf-anstoss knopf-breit">Weitere anzeigen ({{ $gesamt - $zeilen->count() }})</a>
        @endif
    @empty
        <x-leer icon="lightbulb">{{ $suche ? 'Dazu habe ich nichts gefunden. Probier ein anderes Wort.' : 'Hier kommen Impulse und Podcastfolgen hin, sobald es welche gibt.' }}</x-leer>
    @endforelse
</x-layouts.app>

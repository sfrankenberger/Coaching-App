<x-layouts.app title="Suchen">
    <h1>Suchen</h1>
    <form method="get" class="suche mt-3.5 mb-4">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="{{ $q }}" placeholder="Wort oder Thema, auch in Abschriften" aria-label="Suchen" autofocus>
    </form>
    @if ($q !== '' && mb_strlen($q) < 2)
        <p class="hinweis">Mindestens zwei Zeichen.</p>
    @elseif ($q !== '' && $gruppen->isEmpty())
        <x-leer icon="magnifying-glass" knopf="Themen nachschlagen" :href="route('themen.index')">Zu «{{ $q }}» habe ich nichts gefunden. Probier ein anderes Wort oder schau bei den Themen.</x-leer>
    @endif
    @foreach ($gruppen as $g)
        <h2 class="abschnitt"><i class="fa-solid fa-{{ $g['icon'] }}"></i>{{ $g['titel'] }}<em>{{ $g['treffer']->count() }}</em></h2>
        @foreach ($g['treffer'] as $t)
            <a href="{{ $t['url'] }}" class="zeile">
                <span class="tx">
                    <b>{{ $t['titel'] }}</b>
                    <span>{{ $t['wo'] }}</span>
                    @if ($t['text'])<span class="lesetext" style="font-size:var(--fs-md);white-space:normal">{!! preg_replace('~('.preg_quote(e($q), '~').')~iu', '<mark>$1</mark>', e($t['text'])) !!}</span>@endif
                </span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    @endforeach
</x-layouts.app>

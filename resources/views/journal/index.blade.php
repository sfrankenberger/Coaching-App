<x-layouts.app title="Mein Journal">
    <h1 class="mb-1">Mein Journal</h1>
    <p class="unterzeile m-0 mb-3">Dein Platz zum Schreiben. Alles bleibt bei dir, bis du es selbst teilst.</p>

    <div class="flex flex-wrap gap-2 mb-3">
        <a href="{{ route('aufgaben.index') }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-list-check"></i>Aufgaben{{ $offeneAufgaben ? ' · '.$offeneAufgaben.' offen' : '' }}</a>
        <a href="{{ route('notizen.index') }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-note-sticky"></i>Notizen</a>
        <a href="{{ route('reflexion.index') }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-pen-to-square"></i>Reflexion</a>
        <a href="{{ route('projekte.index') }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-lightbulb"></i>Projekte</a>
    </div>

    @if ($naechster)
        <a href="{{ route('termine.show', $naechster) }}" class="zeile">
            <span class="ic"><i class="fa-solid fa-video"></i></span>
            <span class="tx"><b>Als Nächstes: {{ $naechster->title }}</b><span>{{ \App\Support\Zeit::wann($naechster->starts_at) }}{{ $naechster->program ? ' · '.$naechster->program->title : '' }}</span></span>
            <i class="fa-solid fa-chevron-right pf"></i>
        </a>
    @endif

    <div class="pillen">
        <a href="{{ route('journal.index', array_filter(['projekt' => $projekt?->id])) }}" @class(['pille', 'an' => ! $art])>Alles</a>
        @foreach (\App\Programs\Zeitleiste::ARTEN as $k => [$label, $icon])
            <a href="{{ route('journal.index', array_filter(['art' => $k, 'projekt' => $projekt?->id])) }}" @class(['pille', 'an' => $art === $k])><i class="fa-solid fa-{{ $icon }}"></i>{{ $label }}</a>
        @endforeach
    </div>
    @if ($projekte->isNotEmpty())
        <div class="pillen">
            <a href="{{ route('journal.index', array_filter(['art' => $art])) }}" @class(['pille', 'an' => ! $projekt])>Alle Projekte</a>
            @foreach ($projekte as $p)
                <a href="{{ route('journal.index', array_filter(['art' => $art, 'projekt' => $p->id])) }}" @class(['pille', 'an' => $projekt?->id === $p->id]) style="--kc: {{ $p->farbe }}"><span class="punkt"></span>{{ $p->name }}</a>
            @endforeach
        </div>
    @endif

    @php $monat = null; $woche = null; @endphp
    @forelse ($punkte as $x)
        @php $m = $x['zeit']->translatedFormat('F Y'); $w = \App\Programs\Zeitleiste::wocheLabel($x['zeit']); @endphp
        @if ($m !== $monat)
            <h2 class="abschnitt mt-4">{{ $m }}</h2>
            @php $monat = $m; $woche = null; @endphp
        @endif
        @if ($w !== $woche)
            <p class="zl-woche">{{ $w }}</p>
            @php $woche = $w; @endphp
        @endif
        @include('journal._punkt', ['x' => $x])
    @empty
        <x-leer icon="timeline">Noch nichts in der Zeitleiste. Was du schreibst und was in deinen Kursen läuft, sammelt sich hier.</x-leer>
    @endforelse
</x-layouts.app>

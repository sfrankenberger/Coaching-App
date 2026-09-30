<x-layouts.app title="Community">
    <h1 class="mb-1">Community</h1>
    <p class="unterzeile m-0 mb-3">Hier laufen alle Fragen und alles zusammen, was jemand aus den Kursen teilt. Du kannst kommentieren, reagieren und Erfahrungen weitergeben. Was nur {{ $coach }} lesen soll, schreibst du im 1:1 Coaching.</p>

    @if ($alle->isEmpty())
        <x-leer icon="comments">Sobald du in einem Kurs bist, findest du hier die Fragen aus der Gruppe.</x-leer>
    @else
        <div class="flex flex-wrap gap-2 mb-3">
            <a href="{{ route('community.leute', array_filter(['k' => $kurs?->id])) }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-users"></i>Wer ist dabei</a>
            @if ($programme->isEmpty())
                <a href="{{ route('kurse.fragen', [$alle->first(), 'frage' => 1]) }}" class="knopf knopf-klein"><i class="fa-solid fa-plus"></i>Was beschäftigt dich?</a>
            @endif
        </div>

        @if ($programme->isNotEmpty())
            @include('fragen._formular', ['action' => route('community.fragen.store'), 'programme' => $kurs ? collect([$kurs]) : $programme, 'erwaehnbar' => null])
        @endif

        @if ($programme->count() > 0 && $alle->count() > 1)
            <div class="pillen">
                <a href="{{ route('community', array_filter(['f' => $filter, 'sort' => $sort, 'q' => $q])) }}" @class(['pille', 'an' => ! $kurs])>Alle</a>
                @foreach ($programme as $p)
                    <a href="{{ route('community', array_filter(['k' => $p->id, 'f' => $filter, 'sort' => $sort, 'q' => $q])) }}" @class(['pille', 'an' => $kurs?->id === $p->id])>{{ $p->title }}</a>
                @endforeach
            </div>
        @endif

        @include('fragen._liste', ['basis' => ['url' => route('community'), 'fest' => array_filter(['k' => $kurs?->id])], 'mitKurs' => ! $kurs && $alle->count() > 1])

        @if ($geteilt->isNotEmpty())
            <h2 class="abschnitt mt-4"><i class="fa-solid fa-share-nodes"></i>Geteilt aus dem Kurs<em>{{ $geteilt->count() }}</em></h2>
            <p class="hinweis m-0 mb-3">Was andere für den Kurs oder die Community freigeben. Reagier darauf oder schreib etwas dazu.</p>
            @foreach ($geteilt as $g)
                <x-geteilt-karte :item="$g" />
            @endforeach
        @endif
    @endif
</x-layouts.app>

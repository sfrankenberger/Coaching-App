@php
    $icons = ['pdf' => 'file-pdf', 'audio' => 'headphones', 'video' => 'circle-play', 'aufzeichnung' => 'circle-play', 'podcast' => 'microphone', 'link' => 'link', 'text' => 'file-lines', 'image' => 'image'];
@endphp
<x-layouts.app title="Material">
    <h1>Material</h1>

    <div class="filterstreifen">
        <div class="pillen">
            <a href="{{ route('material.index') }}" @class(['pille', 'an' => $filter === ''])>Alles</a>
            @foreach ($kurse as $k)
                <a href="{{ route('material.index', ['f' => 'k'.$k->id]) }}" @class(['pille', 'an' => $filter === 'k'.$k->id]) style="--kc: {{ $k->color ?: 'var(--c-primary)' }}"><span class="punkt"></span>{{ $k->title }}</a>
            @endforeach
            <a href="{{ route('material.index', ['f' => 'aufzeichnung']) }}" @class(['pille', 'an' => $filter === 'aufzeichnung'])><i class="fa-solid fa-circle-play"></i>Aufzeichnungen</a>
            <a href="{{ route('material.index', ['f' => 'gemerkt']) }}" @class(['pille', 'an' => $filter === 'gemerkt'])><i class="fa-solid fa-bookmark"></i>Gemerkt{{ $gemerkt->count() ? ' ('.$gemerkt->count().')' : '' }}</a>
        </div>
    </div>
    <form method="get" class="suche" style="margin:0 0 16px">
        <i class="fa-solid fa-magnifying-glass"></i>
        @if ($filter) <input type="hidden" name="f" value="{{ $filter }}"> @endif
        <input type="search" name="q" value="{{ $suche }}" placeholder="Im Material suchen" aria-label="Im Material suchen">
    </form>

    @forelse ($zeilen as $z)
        @php $key = $z['art'].'-'.$z['id']; $k = $z['kurs'] ? $kurse->firstWhere('id', $z['kurs']) : null; @endphp
        <article class="zeile" style="--kc: {{ $k?->color ?: 'var(--c-primary)' }}">
            <span class="ic"><i class="fa-solid fa-{{ $icons[$z['typ']] ?? 'file' }}"></i></span>
            <div class="tx">
                @if ($z['url'])
                    <a href="{{ $z['url'] }}" @if ($z['art'] === 'resource' && empty($z['seite'])) target="_blank" rel="noopener" @endif class="no-underline text-ink"><b>{{ $z['titel'] }}</b></a>
                @else
                    <b>{{ $z['titel'] }}</b>
                @endif
                <span>
                    {{ $z['typ'] === 'aufzeichnung' ? 'Aufzeichnung' : (\App\Models\Resource::TYPES[$z['typ']] ?? $z['typ']) }}
                    @if ($k) · {{ $k->title }} @endif
                    @if ($z['geteilt']) · Für dich geteilt @endif
                    @if ($z['ts']) · {{ $z['ts']->translatedFormat('j. M Y') }} @endif
                    @if ($z['dauer']) · {{ \App\Support\Zeit::dauerLesbar($z['dauer']) }} @endif
                </span>
            </div>
            <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($key)" />
        </article>
    @empty
        <div class="leer"><i class="fa-regular fa-folder-open"></i>{{ $filter === 'gemerkt' ? 'Noch nichts gemerkt. Tippe bei einem Eintrag auf das Lesezeichen, dann findest du ihn hier wieder.' : 'Nichts in dieser Auswahl.' }}</div>
    @endforelse
</x-layouts.app>

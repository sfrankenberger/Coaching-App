@php
    $icons = ['pdf' => 'file-pdf', 'audio' => 'headphones', 'video' => 'circle-play', 'aufzeichnung' => 'circle-play', 'podcast' => 'microphone', 'link' => 'link', 'text' => 'file-lines', 'image' => 'image'];
@endphp
<x-layouts.app title="Ressourcen">
    <h1>Ressourcen</h1>

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
    <form method="get" class="suche m-0 mb-2">
        <i class="fa-solid fa-magnifying-glass"></i>
        @if ($filter) <input type="hidden" name="f" value="{{ $filter }}"> @endif
        <input type="search" name="q" value="{{ $suche }}" placeholder="Im Material suchen" aria-label="Im Material suchen">
    </form>
    <form method="get" class="flex flex-wrap gap-2 mb-4">
        @if ($filter)<input type="hidden" name="f" value="{{ $filter }}">@endif
        @if ($suche)<input type="hidden" name="q" value="{{ $suche }}">@endif
        <select name="art" class="pille" onchange="this.form.submit()" aria-label="Art">
            <option value="">Alle Arten</option>
            @foreach (['video' => 'Videos', 'aufzeichnung' => 'Aufzeichnungen', 'audio' => 'Audio', 'pdf' => 'PDF', 'link' => 'Links', 'text' => 'Texte', 'image' => 'Bilder'] as $k => $l)<option value="{{ $k }}" @selected($art === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="h" class="pille" onchange="this.form.submit()" aria-label="Herkunft">
            <option value="">Alle Herkünfte</option>
            <option value="kurs" @selected($herkunft === 'kurs')>Aus einem Kurs</option>
            <option value="geteilt" @selected($herkunft === 'geteilt')>Für dich geteilt</option>
            <option value="allgemein" @selected($herkunft === 'allgemein')>Allgemein</option>
        </select>
        <select name="sort" class="pille" onchange="this.form.submit()" aria-label="Sortierung">
            <option value="neu" @selected($sort === 'neu')>Neueste zuerst</option>
            <option value="alt" @selected($sort === 'alt')>Älteste zuerst</option>
            <option value="titel" @selected($sort === 'titel')>Nach Titel</option>
        </select>
    </form>

    @forelse ($zeilen as $z)
        @php $key = $z['art'].'-'.$z['id']; $k = $z['kurs'] ? $kurse->firstWhere('id', $z['kurs']) : null; @endphp
        <article @class(['zeile', 'regal-karte' => ! empty($z['bild'])]) style="--kc: {{ $k?->color ?: 'var(--c-primary)' }}">
            @if (! empty($z['bild']))
                <a href="{{ $z['url'] }}" class="regal-bild"><img src="{{ $z['bild'] }}" alt="" loading="lazy"></a>
            @else
                <span class="ic"><i class="fa-solid fa-{{ $icons[$z['typ']] ?? 'file' }}"></i></span>
            @endif
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
                    @if (! empty($z['datei'])) · {{ $z['datei'] }} @endif
                    @if (! empty($z['gehoert'])) · in {{ $z['gehoert'] }} {{ $z['gehoert'] === 1 ? 'Lektion' : 'Lektionen' }} @endif
                    @if (! empty($z['angeschaut'])) · <i class="fa-solid fa-circle-check" style="color:var(--c-success)"></i> Angeschaut @elseif (! empty($z['prozent'])) · {{ $z['prozent'] }} % gesehen @endif
                </span>
                @if (! empty($z['text']) && ! empty($z['bild']))<span class="hinweis block mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($z['text']), 120) }}</span>@endif
            </div>
            <span class="flex items-center gap-1">
                @if ($z['art'] === 'resource')<a href="{{ route('gespraech.index', ['ref' => 'resource:'.$z['id']]) }}" class="merken" title="Im Gespräch teilen" aria-label="Teilen"><i class="fa-solid fa-share-nodes"></i></a>@endif
                <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($key)" />
            </span>
        </article>
    @empty
        <div class="leer"><i class="fa-regular fa-folder-open"></i>{{ $filter === 'gemerkt' ? 'Noch nichts gemerkt. Tippe bei einem Eintrag auf das Lesezeichen, dann findest du ihn hier wieder.' : 'Nichts in dieser Auswahl.' }}</div>
    @endforelse
</x-layouts.app>

@props(['z', 'gemerkt'])
@php
    $key = $z['art'].'-'.$z['id'];
    $frage = in_array($z['art'], ['post', 'episode'], true) ? 'Frage zu «'.$z['titel'].'»: ' : null;
@endphp
@if ($z['bild'])
    <article class="karte impuls-karte">
        <a href="{{ $z['url'] }}" class="impuls-bild" style="--bild: url('{{ $z['bild'] }}')">
            <img src="{{ $z['bild'] }}" alt="" loading="lazy">
            <span class="chip"><i class="fa-solid fa-{{ $z['art'] === 'episode' ? 'microphone' : 'lightbulb' }}"></i>{{ $z['typ'] }}</span>
        </a>
        <div class="impuls-text">
            <a href="{{ $z['url'] }}" class="t no-underline">{{ $z['titel'] }}</a>
            @if ($z['text'])<p class="x m-0 mt-1.5">{{ \Illuminate\Support\Str::limit(strip_tags($z['text']), 170) }}</p>@endif
            <div class="impuls-fuss">
                <span class="m">{{ $z['ts']?->translatedFormat('j. M Y') }}</span>
                <span class="flex items-center gap-1">
                    @if ($frage)<a href="{{ route('gespraech.index', ['entwurf' => $frage]) }}" class="impuls-frage"><i class="fa-solid fa-circle-question"></i>Frage dazu</a>@endif
                    <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($key)" />
                </span>
            </div>
        </div>
    </article>
@else
    <article class="zeile">
        <span class="ic"><i class="fa-solid fa-{{ match ($z['art']) { 'episode' => 'microphone', 'unit', 'step', 'program' => 'graduation-cap', 'resource' => 'folder-open', 'event' => 'calendar', default => 'lightbulb' } }}"></i></span>
        <div class="tx">
            <a href="{{ $z['url'] }}" class="no-underline text-ink"><b>{{ $z['titel'] }}</b></a>
            <span>{{ $z['typ'] }}@if ($z['ts']) · {{ $z['ts']->translatedFormat('j. M Y') }}@endif</span>
        </div>
        <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($key)" />
    </article>
@endif

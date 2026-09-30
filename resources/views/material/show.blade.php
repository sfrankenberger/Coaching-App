<x-layouts.app :title="$r->title">
    @php
        $ziel = $r->target();
        $video = $r->type === 'video' ? \App\Support\Video::embed($r->url) : null;
        $audio = in_array($r->type, ['audio', 'podcast'], true) && $ziel;
    @endphp
    <div style="--kc: {{ $kurs?->color ?: 'var(--c-primary)' }}">
        <p class="m-0 mb-2"><a href="{{ route('material.index', $kurs ? ['f' => 'k'.$kurs->id] : []) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Material</a></p>
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <span class="eyebrow">{{ $r->typeLabel() }}@if ($kurs) · {{ $kurs->title }}@endif@if ($r->duration) · {{ \App\Support\Zeit::dauerLesbar($r->duration) }}@endif</span>
                <h1 class="m-0 mt-0.5">{{ $r->title }}</h1>
            </div>
            <a href="{{ route('gespraech.index', ['ref' => 'resource:'.$r->id]) }}" class="merken" title="Im Gespräch teilen" aria-label="Teilen"><i class="fa-solid fa-share-nodes"></i></a>
            <x-merken art="resource" :id="$r->id" :an="$gemerkt" />
        </div>
        @if ($einheiten->isNotEmpty())
            <p class="hinweis m-0 mt-1.5"><i class="fa-solid fa-graduation-cap"></i> Gehört zu: @foreach ($einheiten as $u)<a href="{{ route('kurse.einheit', [$u->program, $u]) }}">{{ $u->title }}</a>@if (! $loop->last), @endif @endforeach</p>
        @endif
        @if ($r->description)<p class="unterzeile m-0 mt-1.5">{{ $r->description }}</p>@endif

        @if ($position && ! $angeschaut && ($video || $audio))<p class="hinweis m-0 mt-3"><i class="fa-solid fa-clock-rotate-left"></i> Du warst bei {{ gmdate($position >= 3600 ? 'G:i:s' : 'i:s', $position) }}, es geht dort weiter.</p>@endif
        @if ($video)
            <div class="video" data-medien="resource-{{ $r->id }}" data-start="{{ $angeschaut ? 0 : (int) $position }}" style="margin-top:14px">
                @if ($video['kind'] === 'iframe')<iframe src="{{ $video['src'] }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="{{ $r->title }}"></iframe>@else<video controls preload="metadata" src="{{ $video['src'] }}" @if ($r->image_url) poster="{{ $r->image_url }}" @endif></video>@endif
            </div>
        @elseif ($audio)
            <div class="karte" data-medien="resource-{{ $r->id }}" data-start="{{ $angeschaut ? 0 : (int) $position }}" style="margin-top:14px">
                @if ($r->image_url)<img src="{{ $r->image_url }}" alt="" style="width:100%;border-radius:12px;margin-bottom:10px" loading="lazy">@endif
                <audio class="w-full" controls preload="metadata" src="{{ $ziel }}"></audio>
            </div>
        @elseif ($ziel)
            <a href="{{ $ziel }}" target="_blank" rel="noopener" class="knopf mt-3.5"><i class="fa-solid fa-arrow-up-right-from-square"></i>Öffnen</a>
        @endif
        @if ($video || $audio)
            <div class="flex flex-wrap items-center gap-2 mt-2.5">
                @if ($angeschaut)<span class="chip chip-gut"><i class="fa-solid fa-circle-check"></i>Angeschaut</span>@endif
                <p class="meldung meldung-gut m-0" data-erledigt-hinweis hidden style="flex-basis:100%"><i class="fa-solid fa-circle-check"></i> Fast fertig, als angeschaut markiert.</p>
                <form method="post" action="{{ route('material.gesehen', $r) }}" class="ml-auto">@csrf<button type="submit" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-{{ $angeschaut ? 'rotate-left' : 'check' }}"></i>{{ $angeschaut ? 'Nochmal ansehen' : 'Als angeschaut markieren' }}</button></form>
            </div>
        @endif

        @if ($r->summary)
            <h2 class="abschnitt"><i class="fa-solid fa-list-ul"></i>Worum es geht</h2>
            <div class="karte">
                @if ($video || $audio)<x-kapitel :text="$r->summary" />@endif
                <div class="prose-app">{{ \App\Support\Kapitel::html($r->summary) }}</div>
            </div>
            <p class="hinweis m-0 mt-1.5">Tipp auf eine Zeitmarke, dann springt das Video an die Stelle. Die Zusammenfassung ist automatisch erstellt.</p>
        @endif
        @if ($r->body && strip_tags($r->body) !== '')
            <div class="karte mt-3.5"><div class="prose-app">{!! $r->body !!}</div></div>
        @endif
        @if ($r->transcript)
            <details class="karte mt-3.5">
                <summary class="t cursor-pointer">Abschrift</summary>
                <div class="lesetext whitespace-pre-line" style="margin-top:10px;font-size:var(--fs-md)">{{ $r->transcript }}</div>
            </details>
        @endif
    </div>
</x-layouts.app>

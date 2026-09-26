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
            <x-merken art="resource" :id="$r->id" :an="$gemerkt" />
        </div>
        @if ($r->description)<p class="unterzeile m-0 mt-1.5">{{ $r->description }}</p>@endif

        @if ($video)
            <div class="video" data-medien="resource-{{ $r->id }}" style="margin-top:14px">
                @if ($video['kind'] === 'iframe')<iframe src="{{ $video['src'] }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="{{ $r->title }}"></iframe>@else<video controls preload="metadata" src="{{ $video['src'] }}" @if ($r->image_url) poster="{{ $r->image_url }}" @endif></video>@endif
            </div>
        @elseif ($audio)
            <div class="karte" data-medien="resource-{{ $r->id }}" style="margin-top:14px">
                @if ($r->image_url)<img src="{{ $r->image_url }}" alt="" style="width:100%;border-radius:12px;margin-bottom:10px" loading="lazy">@endif
                <audio class="w-full" controls preload="metadata" src="{{ $ziel }}"></audio>
            </div>
        @elseif ($ziel)
            <a href="{{ $ziel }}" target="_blank" rel="noopener" class="knopf mt-3.5"><i class="fa-solid fa-arrow-up-right-from-square"></i>Öffnen</a>
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

<x-layouts.app :title="$r->title">
    @php
        $ziel = $r->target();
        $video = $r->type === 'video' ? \App\Support\Video::embed($r->url) : null;
        $audio = in_array($r->type, ['audio', 'podcast'], true) && $ziel;
    @endphp
    <div style="--kc: {{ $kurs?->color ?: 'var(--c-primary)' }}">
        <p style="margin:0 0 8px"><a href="{{ route('material.index', $kurs ? ['f' => 'k'.$kurs->id] : []) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Material</a></p>
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <span class="eyebrow">{{ $r->typeLabel() }}@if ($kurs) · {{ $kurs->title }}@endif@if ($r->duration) · {{ $r->duration }}@endif</span>
                <h1 style="margin:2px 0 0">{{ $r->title }}</h1>
            </div>
            <x-merken art="resource" :id="$r->id" :an="$gemerkt" />
        </div>
        @if ($r->description)<p class="unterzeile" style="margin:6px 0 0">{{ $r->description }}</p>@endif

        @if ($video)
            <div class="video" data-medien="resource-{{ $r->id }}" style="margin-top:14px">
                @if ($video['kind'] === 'iframe')<iframe src="{{ $video['src'] }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="{{ $r->title }}"></iframe>@else<video controls preload="metadata" src="{{ $video['src'] }}" @if ($r->image_url) poster="{{ $r->image_url }}" @endif></video>@endif
            </div>
        @elseif ($audio)
            <div class="karte" data-medien="resource-{{ $r->id }}" style="margin-top:14px">
                @if ($r->image_url)<img src="{{ $r->image_url }}" alt="" style="width:100%;border-radius:12px;margin-bottom:10px" loading="lazy">@endif
                <audio controls preload="metadata" src="{{ $ziel }}" style="width:100%"></audio>
            </div>
        @elseif ($ziel)
            <a href="{{ $ziel }}" target="_blank" rel="noopener" class="knopf" style="margin-top:14px"><i class="fa-solid fa-arrow-up-right-from-square"></i>Öffnen</a>
        @endif

        @if ($r->summary)
            <h2 class="abschnitt"><i class="fa-solid fa-list-ul"></i>Worum es geht</h2>
            <div class="karte"><div class="prose-app">{{ \App\Support\Kapitel::html($r->summary) }}</div></div>
            <p class="hinweis" style="margin:6px 0 0">Tipp auf eine Zeitmarke, dann springt das Video an die Stelle. Die Zusammenfassung ist automatisch erstellt.</p>
        @endif
        @if ($r->body && strip_tags($r->body) !== '')
            <div class="karte" style="margin-top:14px"><div class="prose-app">{!! $r->body !!}</div></div>
        @endif
        @if ($r->transcript)
            <details class="karte" style="margin-top:14px">
                <summary class="t" style="cursor:pointer">Abschrift</summary>
                <div class="x whitespace-pre-line" style="margin-top:10px;font-size:var(--fs-md)">{{ $r->transcript }}</div>
            </details>
        @endif
    </div>
</x-layouts.app>

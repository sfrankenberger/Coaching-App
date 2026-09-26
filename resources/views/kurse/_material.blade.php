{{-- Material direkt in der Seite: PDF eingebettet, Audio mit Player, Video eingebettet, sonst eine Zeile zum Oeffnen --}}
@php
    $ziel = $r->target();
    $istDatei = filled($r->file_path);
    $pdf = $r->type === 'pdf' && $ziel;
    $audio = in_array($r->type, ['audio', 'podcast'], true) && $ziel;
    $video = $r->type === 'video' ? \App\Support\Video::embed($r->url) : null;
    $icon = ['pdf' => 'file-pdf', 'audio' => 'headphones', 'video' => 'circle-play', 'podcast' => 'microphone', 'link' => 'link', 'text' => 'file-lines', 'image' => 'image'][$r->type] ?? 'file';
@endphp
<div class="karte">
    <div class="flex items-center gap-3">
        <span class="zeile-ic" style="flex:0 0 38px;width:38px;height:38px;border-radius:50%;background:var(--c-primary-tint);color:var(--c-primary);display:grid;place-items:center"><i class="fa-solid fa-{{ $icon }}"></i></span>
        <span class="min-w-0 flex-1">
            <span class="t">{{ $r->title }}</span>
            <span class="m">{{ $r->typeLabel() }}@if ($r->description) · {{ \Illuminate\Support\Str::limit(strip_tags($r->description), 80) }}@endif</span>
        </span>
        @if ($r->hatSeite())
            <a href="{{ route('material.show', $r) }}" class="knopf knopf-rund" style="background:var(--c-neutral)" aria-label="Eigene Seite"><i class="fa-solid fa-expand"></i></a>
        @elseif ($ziel)
            <a href="{{ $ziel }}" target="_blank" rel="noopener" class="knopf knopf-rund" style="background:var(--c-neutral)" aria-label="Öffnen oder herunterladen"><i class="fa-solid fa-{{ $istDatei ? 'download' : 'arrow-up-right-from-square' }}"></i></a>
        @endif
    </div>
    @if ($pdf)
        <iframe src="{{ $ziel }}#view=FitH" title="{{ $r->title }}" loading="lazy" style="width:100%;height:min(75vh,760px);border:1px solid var(--c-card-border);border-radius:12px;margin-top:12px;background:#fff"></iframe>
    @elseif ($audio)
        <div data-medien="resource-{{ $r->id }}" style="margin-top:10px"><audio class="w-full" controls preload="none" src="{{ $ziel }}"></audio></div>
    @elseif ($video)
        <div class="video" data-medien="resource-{{ $r->id }}" style="margin-top:12px">
            @if ($video['kind'] === 'iframe')<iframe src="{{ $video['src'] }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" title="{{ $r->title }}"></iframe>@else<video controls preload="metadata" src="{{ $video['src'] }}"></video>@endif
        </div>
    @endif
    @if ($r->summary)
        <details class="mt-2.5">
            <summary class="hinweis cursor-pointer"><i class="fa-solid fa-list-ul"></i> Worum es geht</summary>
            <div class="prose-app mt-2">{{ \App\Support\Kapitel::html($r->summary) }}</div>
        </details>
    @endif
</div>

<x-layouts.app :title="$event->title">
    @php $rec = $event->hasRecording() ? \App\Support\Video::embed($event->recording_url) : null; $live = $event->isLive(); $ab = $mein?->status === 'declined'; @endphp
    <p class="mb-2"><a href="{{ route('termine.index') }}" class="hinweis no-underline">&larr; Termine</a></p>

    <x-karte>
        <span class="hinweis uppercase tracking-wider text-xs font-semibold">{{ $event->typeLabel() }}@if ($event->program) · {{ $event->program->title }}@endif</span>
        <h1>{{ $event->title }}</h1>
        <p class="text-ink-soft mt-1">
            {{ $event->starts_at->translatedFormat('l, j. F Y') }}@if (! $event->all_day), {{ $event->starts_at->format('H:i') }}@if ($event->ends_at) bis {{ $event->ends_at->format('H:i') }}@endif Uhr @else, ganzer Tag @endif
            @if ($event->location) · {{ $event->location }} @endif
        </p>
        @if ($event->description)
            <div class="prose-app mt-3">{!! $event->description !!}</div>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            @if (! $event->isPast() && $event->zoom_url)
                <a href="{{ $event->zoom_url }}" target="_blank" rel="noopener" class="knopf">{{ $live ? 'Jetzt beitreten' : 'Zoom öffnen' }}</a>
            @endif
            @if (! $event->isPast() && ! $event->isOneOnOne())
                <form method="post" action="{{ route('termine.dabei', $event) }}">@csrf<button class="knopf knopf-leise">{{ $ab ? 'Doch dabei' : 'Nicht dabei' }}</button></form>
            @endif
            @if ($event->isPast() && ! $event->isOneOnOne() && $mein?->status !== 'attended')
                <form method="post" action="{{ route('termine.gesehen', $event) }}">@csrf<input type="hidden" name="status" value="attended"><button class="knopf knopf-leise">Ich war live dabei</button></form>
            @endif
        </div>
        @if ($absagen->isNotEmpty() && ! $event->isPast())
            <p class="hinweis mt-3">Nicht dabei: {{ $absagen->map(fn ($a) => $a->user->vorname())->join(', ') }}</p>
        @endif
    </x-karte>

    @if ($event->hasRecording())
        <x-karte class="!p-2">
            @if ($rec)
                <div class="video">
                    @if ($rec['kind'] === 'iframe')
                        <iframe src="{{ $rec['src'] }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" title="Aufzeichnung"></iframe>
                    @else
                        <video controls preload="metadata" src="{{ $rec['src'] }}"></video>
                    @endif
                </div>
            @else
                <a href="{{ $event->recording_url }}" target="_blank" rel="noopener" class="knopf m-2">Aufzeichnung öffnen</a>
            @endif
            <div class="flex flex-wrap items-center gap-3 p-2">
                <span class="hinweis">Aufzeichnung{{ $event->recording_duration ? ' · '.$event->recording_duration : '' }}</span>
                @if ($mein?->status !== 'watched' && $mein?->status !== 'attended')
                    <form method="post" action="{{ route('termine.gesehen', $event) }}" class="ml-auto">@csrf<button class="knopf knopf-leise" style="min-height:36px;padding:6px 12px">Gesehen</button></form>
                @else
                    <span class="hinweis ml-auto text-success font-semibold">✓ {{ $mein->status === 'attended' ? 'Live dabei' : 'Gesehen' }}</span>
                @endif
            </div>
        </x-karte>
    @elseif ($event->isPast() && ! $event->all_day)
        <x-karte><p class="hinweis">Die Aufzeichnung kommt in den nächsten Tagen.</p></x-karte>
    @endif

    @if ($event->summary)
        <x-karte titel="Zusammenfassung"><div class="prose-app whitespace-pre-line">{{ $event->summary }}</div></x-karte>
    @endif

    @if ($event->resources->isNotEmpty())
        <x-karte titel="Material zum Termin">
            <ul class="divide-y divide-line">
                @foreach ($event->resources as $r)
                    <li class="flex items-center gap-3 py-2">
                        <span class="hinweis uppercase w-12 shrink-0">{{ $r->typeLabel() }}</span>
                        <a href="{{ $r->target() }}" target="_blank" rel="noopener" class="flex-1 min-w-0">{{ $r->title }}</a>
                    </li>
                @endforeach
            </ul>
        </x-karte>
    @endif
</x-layouts.app>

<x-layouts.app :title="$event->title">
    @php $rec = $event->hasRecording() ? \App\Support\Video::embed($event->recording_url) : null; $live = $event->isLive(); $ab = $mein?->status === 'declined'; @endphp
    <p style="margin:0 0 8px"><a href="{{ route('termine.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Termine</a></p>

    <x-karte>
        <span class="eyebrow">{{ $event->typeLabel() }}@if ($event->program) · {{ $event->program->title }}@endif</span>
        <h1 style="margin:4px 0 0">{{ $event->title }}</h1>
        @if ($live)<span class="badge badge-live" style="margin-top:8px"><i class="fa-solid fa-circle" style="font-size:7px"></i>Läuft gerade</span>@endif
        <p class="x" style="margin:6px 0 0">
            {{ $event->starts_at->translatedFormat('l, j. F Y') }}@if (! $event->all_day), {{ $event->starts_at->format('H:i') }}@if ($event->ends_at) bis {{ $event->ends_at->format('H:i') }}@endif Uhr @else, ganzer Tag @endif
            @if ($event->location) · {{ $event->location }} @endif
        </p>
        @if ($event->description)
            <div class="prose-app mt-3">{!! $event->description !!}</div>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <x-merken art="event" :id="$event->id" :an="\App\Models\Bookmark::where('user_id', auth()->id())->where('bookmarkable_type', 'event')->where('bookmarkable_id', $event->id)->exists()" :text="true" />
            @if (! $event->isPast() && $event->zoom_url)
                <a href="{{ $event->zoom_url }}" target="_blank" rel="noopener" class="knopf"><i class="fa-solid fa-video"></i>{{ $live ? 'Jetzt beitreten' : 'Zoom öffnen' }}</a>
            @endif
            @if (! $event->isPast())
                <a href="{{ route('termine.ics', $event) }}" class="knopf knopf-ruhig"><i class="fa-solid fa-calendar-plus"></i>In den Kalender</a>
            @endif
            @php $buchung = $event->isOneOnOne() && ! $event->isPast() ? \App\Models\Booking::where('event_id', $event->id)->where('status', 'gebucht')->where('user_id', auth()->id())->first() : null; @endphp
            @if ($buchung)
                <form method="post" action="{{ route('buchen.absagen', $buchung) }}" onsubmit="return confirm('Diesen Termin absagen?')">@csrf<button class="knopf knopf-leise">Absagen</button></form>
            @endif
            @if (! $event->isPast() && ! $event->isOneOnOne())
                <form method="post" action="{{ route('termine.dabei', $event) }}">@csrf<button class="knopf knopf-leise">{{ $ab ? 'Doch dabei' : 'Nicht dabei' }}</button></form>
            @endif
            @if ($event->isPast() && ! $event->isOneOnOne() && $mein?->status !== 'attended')
                <form method="post" action="{{ route('termine.gesehen', $event) }}">@csrf<input type="hidden" name="status" value="attended"><button class="knopf knopf-leise">Ich war live dabei</button></form>
            @endif
        </div>
        @if ($absagen->isNotEmpty() && ! $event->isPast())
            <p class="hinweis mt-3">Nicht dabei: {{ auth()->user()->canManageCurrentTenant() ? $absagen->map(fn ($a) => $a->user->vorname())->join(', ') : $absagen->map(fn ($a) => $a->user->kuerzel())->join(', ') }}</p>
        @endif
    </x-karte>

    @if ($event->hasRecording())
        <x-karte class="!p-2">
            @if ($rec)
                @if ($position)<p class="hinweis" style="margin:4px 6px 8px"><i class="fa-solid fa-clock-rotate-left"></i> Du warst bei {{ gmdate($position >= 3600 ? 'G:i:s' : 'i:s', $position) }}, es geht dort weiter.</p>@endif
                <div class="video" id="video-player" data-medien="event-{{ $event->id }}" data-start="{{ (int) $position }}">
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
                <span class="hinweis">Aufzeichnung{{ $event->recording_duration ? ' · '.\App\Support\Zeit::dauerLesbar($event->recording_duration) : '' }}</span>
                @if ($mein?->status !== 'watched' && $mein?->status !== 'attended')
                    <form method="post" action="{{ route('termine.gesehen', $event) }}" class="ml-auto">@csrf<button class="knopf knopf-leise knopf-klein">Gesehen</button></form>
                @else
                    <span class="chip chip-gut ml-auto"><i class="fa-solid fa-check"></i>{{ $mein->status === 'attended' ? 'Live dabei' : 'Gesehen' }}</span>
                @endif
            </div>
        </x-karte>
    @elseif ($event->isPast() && ! $event->all_day)
        <x-karte><p class="hinweis">Die Aufzeichnung kommt in den nächsten Tagen.</p></x-karte>
    @endif

    @if ($event->summary)
        <x-karte titel="Zusammenfassung" icon="align-left">
            <div class="prose-app">{{ \App\Support\Kapitel::html($event->summary) }}</div>
            @if ($vorschlaege?->tasks)
                <p class="eyebrow" style="margin:18px 0 6px">Deine Aufgaben daraus</p>
                <ul class="divide-y divide-line">
                    @foreach ($vorschlaege->tasks as $i => $t)
                        <li class="flex items-start gap-3 py-2">
                            <div class="min-w-0 flex-1">
                                <span class="block text-base">{{ $t['titel'] }}</span>
                                @if ($t['text'])<span class="hinweis block">{{ $t['text'] }}</span>@endif
                            </div>
                            <form method="post" action="{{ route('termine.aufgabe', $event) }}">@csrf<input type="hidden" name="nr" value="{{ $i }}"><button class="knopf knopf-leise knopf-klein">Als Aufgabe</button></form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-karte>
    @endif

    @if ($event->resources->isNotEmpty())
        <x-karte titel="Material zum Termin" icon="folder-open">
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

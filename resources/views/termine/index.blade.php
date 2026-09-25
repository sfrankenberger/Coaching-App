<x-layouts.app title="Termine">
    <h1>{{ $zeit === 'vorbei' ? 'Vergangene Termine' : 'Deine nächsten Termine' }}</h1>

    <div class="flex flex-wrap gap-2 m-0 mb-3.5">
        @if ($kalenderUrl)
            <a href="{{ preg_replace('~^https?://~', 'webcal://', $kalenderUrl) }}" class="knopf knopf-dunkel"><i class="fa-solid fa-calendar-plus"></i>Alle Termine abonnieren</a>
        @endif
        @if ($buchenUrl)
            <a href="{{ $buchenUrl }}" @unless (str_starts_with($buchenUrl, url('/'))) target="_blank" rel="noopener" @endunless class="knopf"><i class="fa-solid fa-calendar-check"></i>Termin buchen</a>
        @endif
    </div>

    <form method="get" class="pillen">
        @foreach (['kommend' => 'Kommende', 'vorbei' => 'Vergangene', 'alle' => 'Alle'] as $k => $label)
            <a href="{{ route('termine.index', array_filter(['zeit' => $k, 'kurs' => $kurs])) }}" @class(['pille', 'an' => $zeit === $k])>{{ $label }}</a>
        @endforeach
        @if ($kurse->count() > 1)
            <input type="hidden" name="zeit" value="{{ $zeit }}">
            <select name="kurs" class="pille" onchange="this.form.submit()" aria-label="Kurs">
                <option value="">Alle Kurse</option>
                @foreach ($kurse as $id => $titel)
                    <option value="{{ $id }}" @selected($kurs === $id)>{{ $titel }}</option>
                @endforeach
            </select>
        @endif
    </form>

    @php $monat = ''; @endphp
    @forelse ($events as $event)
        @php
            $m = $event->starts_at->translatedFormat('F Y');
            $mein = $event->attendees->first();
            $ab = $mein?->status === 'declined';
            $live = $event->isLive();
        @endphp
        @if ($m !== $monat)
            @php $monat = $m; @endphp
            <h2 class="abschnitt"><i class="fa-solid fa-calendar"></i>{{ $m }}</h2>
        @endif
        <article @class(['karte flex items-start gap-3', 'heute' => $live, 'fertig' => $ab]) style="--kc: {{ $event->program?->color ?: 'var(--c-primary)' }}">
            <a href="{{ route('termine.show', $event) }}" class="w-11 shrink-0 text-center no-underline text-ink" style="padding-top:2px">
                <span class="block font-heading text-2xl leading-none">{{ $event->starts_at->format('j') }}</span>
                <span class="eyebrow">{{ $event->starts_at->translatedFormat('D') }}</span>
            </a>
            <div class="min-w-0 flex-1">
                <a href="{{ route('termine.show', $event) }}" class="t no-underline">{{ $event->title }}</a>
                <span class="m">
                    {{ $event->all_day ? 'ganzer Tag' : $event->starts_at->format('H:i').' Uhr' }}
                    @if ($event->isOneOnOne()) · 1:1 @elseif ($event->program) · {{ $event->program->title }} @endif
                    @if ($ab) · Du bist nicht dabei @endif
                </span>
                @if ($live || $event->hasRecording() || ($mein && $mein->attended_at))
                    <span class="flex flex-wrap gap-1 mt-1.5">
                        @if ($live)<span class="badge badge-live"><i class="fa-solid fa-circle" style="font-size:7px"></i>Live</span>@endif
                        @if ($event->hasRecording())<span class="badge badge-rec"><i class="fa-solid fa-circle-play"></i>Aufzeichnung</span>@endif
                        @if ($mein && $mein->attended_at)<span class="badge badge-aufgabe ok"><i class="fa-solid fa-check"></i>Dabei</span>@endif
                    </span>
                @endif
                @if ($event->isPast() && $event->summary)
                    <p class="x m-0 mt-1.5">{{ \Illuminate\Support\Str::words(trim(html_entity_decode(strip_tags($event->summary), ENT_QUOTES, 'UTF-8')), 35) }}</p>
                @endif
            </div>
            <div class="flex shrink-0 gap-1">
                @if (! $event->isPast() && $event->zoom_url)
                    <a href="{{ $event->zoom_url }}" target="_blank" rel="noopener" class="knopf knopf-klein"><i class="fa-solid fa-video"></i>{{ $live ? 'Beitreten' : 'Zoom' }}</a>
                @elseif ($event->hasRecording())
                    <a href="{{ route('termine.show', $event) }}" class="knopf knopf-ruhig knopf-klein">Ansehen</a>
                @endif
            </div>
        </article>
    @empty
        <x-leer icon="calendar" :knopf="$zeit === 'kommend' ? 'Vergangene Termine' : null" :href="route('termine.index', ['zeit' => 'vorbei'])">{{ $zeit === 'kommend' ? 'Gerade steht nichts an. Sobald ein Termin eingetragen ist, bekommst du Bescheid.' : 'Hier steht gerade nichts.' }}</x-leer>
    @endforelse
</x-layouts.app>

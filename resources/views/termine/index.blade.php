<x-layouts.app title="Termine">
    <h1 class="mb-3">Termine</h1>

    <form method="get" class="flex flex-wrap gap-2 mb-3">
        @foreach (['kommend' => 'Kommende', 'vorbei' => 'Vergangene', 'alle' => 'Alle'] as $k => $label)
            <a href="{{ route('termine.index', array_filter(['zeit' => $k, 'kurs' => $kurs])) }}" @class(['knopf', 'knopf-leise' => $zeit !== $k])" style="min-height:36px;padding:6px 14px">{{ $label }}</a>
        @endforeach
        @if ($kurse->count() > 1)
            <select name="kurs" class="feld" style="width:auto" onchange="this.form.submit()">
                <input type="hidden" name="zeit" value="{{ $zeit }}">
                <option value="">Alle Kurse</option>
                @foreach ($kurse as $id => $titel)
                    <option value="{{ $id }}" @selected($kurs === $id)>{{ $titel }}</option>
                @endforeach
            </select>
            <input type="hidden" name="zeit" value="{{ $zeit }}">
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
            <h2 class="mt-4 mb-2 text-lg text-muted">{{ $m }}</h2>
        @endif
        <article @class(['karte flex items-center gap-3', 'border-primary' => $live, 'opacity-70' => $ab])>
            <a href="{{ route('termine.show', $event) }}" class="w-12 shrink-0 text-center no-underline text-ink">
                <span class="block font-heading text-2xl leading-none">{{ $event->starts_at->format('j') }}</span>
                <span class="hinweis uppercase">{{ $event->starts_at->translatedFormat('D') }}</span>
            </a>
            <div class="min-w-0 flex-1">
                <a href="{{ route('termine.show', $event) }}" class="block text-base leading-snug no-underline text-ink">{{ $event->title }}</a>
                <span class="hinweis block">
                    {{ $event->all_day ? 'ganzer Tag' : $event->starts_at->format('H:i').' Uhr' }}
                    @if ($event->isOneOnOne()) · 1:1 @elseif ($event->program) · {{ $event->program->title }} @endif
                    @if ($live) · <b class="text-primary">Läuft gerade</b> @endif
                    @if ($ab) · Du bist nicht dabei @endif
                    @if ($event->hasRecording()) · Aufzeichnung da @endif
                </span>
            </div>
            <div class="flex shrink-0 gap-1">
                @if (! $event->isPast() && $event->zoom_url)
                    <a href="{{ $event->zoom_url }}" target="_blank" rel="noopener" class="knopf" style="min-height:36px;padding:6px 12px">{{ $live ? 'Beitreten' : 'Zoom' }}</a>
                @elseif ($event->hasRecording())
                    <a href="{{ route('termine.show', $event) }}" class="knopf knopf-leise" style="min-height:36px;padding:6px 12px">Ansehen</a>
                @endif
            </div>
        </article>
    @empty
        <x-karte><p class="text-ink-soft">Hier steht gerade nichts.</p></x-karte>
    @endforelse
</x-layouts.app>

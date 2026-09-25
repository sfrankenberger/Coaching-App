<x-layouts.app title="Termin buchen">
    <h1>Termin buchen</h1>
    <p class="unterzeile" style="margin:0 0 16px">Such dir aus, was du brauchst, dann eine Zeit, die dir passt.</p>

    @if ($kontingent)
        <div class="karte">
            <span class="eyebrow"><i class="fa-solid fa-ticket"></i> Deine Sitzungen</span>
            <div class="flex items-baseline gap-2" style="margin-top:4px">
                <b style="font-family:var(--font-heading);font-size:26px;font-weight:400">{{ $kontingent['offen'] }}</b>
                <span class="x">von {{ $kontingent['gesamt'] }} noch offen</span>
            </div>
            <span class="balken" style="display:block;margin:10px 0 0"><span style="width: {{ round(($kontingent['gehabt'] + $kontingent['geplant']) / max(1, $kontingent['gesamt']) * 100) }}%"></span></span>
        </div>
    @endif

    @if ($buchungen->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-calendar-check"></i>Gebucht</h2>
        @foreach ($buchungen as $b)
            <div class="karte flex items-center gap-3">
                <a href="{{ $b->event ? route('termine.show', $b->event) : '#' }}" class="min-w-0 flex-1 no-underline">
                    <span class="t">{{ $b->type?->title ?? $b->event?->title }}</span>
                    <span class="m">{{ $b->starts_at->translatedFormat('l, j. F, H:i') }} Uhr</span>
                </a>
                <form method="post" action="{{ route('buchen.absagen', $b) }}" onsubmit="return confirm('Diesen Termin absagen?')">
                    @csrf
                    <button class="knopf knopf-ruhig knopf-klein">Absagen</button>
                </form>
            </div>
        @endforeach
    @endif

    <h2 class="abschnitt"><i class="fa-solid fa-mug-hot"></i>Was möchtest du buchen?</h2>
    @forelse ($arten as $art)
        @if ($art->hindernis)
            <div class="karte fertig">
                <span class="t">{{ $art->title }}</span>
                <span class="m">{{ $art->duration }} Minuten · {{ $art->hindernis }}</span>
            </div>
        @else
            <a href="{{ route('buchen.zeiten', $art) }}" class="karte block no-underline">
                <span class="flex items-center gap-3">
                    <span class="min-w-0 flex-1">
                        <span class="t">{{ $art->title }}</span>
                        <span class="m">{{ $art->duration }} Minuten</span>
                        @if ($art->text)<span class="x block" style="margin-top:6px">{{ $art->text }}</span>@endif
                    </span>
                    <i class="fa-solid fa-chevron-right" style="color:var(--c-ghost)"></i>
                </span>
            </a>
        @endif
    @empty
        <div class="leer"><i class="fa-regular fa-calendar"></i>Im Moment gibt es nichts zu buchen.</div>
    @endforelse
</x-layouts.app>

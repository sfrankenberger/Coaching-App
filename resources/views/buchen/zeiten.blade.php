@php $booking ??= null; @endphp
<x-layouts.app :title="$booking ? 'Neue Zeit wählen' : $art->title">
    @if ($booking)
        <p class="m-0 mb-2"><a href="{{ route('termine.show', $booking->event_id) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Zum Termin</a></p>
        <h1 class="m-0 mb-1">Neue Zeit wählen</h1>
        <p class="unterzeile m-0 mb-4">{{ $art->title }}, bisher {{ \App\Support\Zeit::wann($booking->starts_at) }}. Der Termin wird umgelegt, es entsteht kein zweiter.</p>
    @else
        <p class="m-0 mb-2"><a href="{{ route('buchen.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Termin buchen</a></p>
        <h1 class="m-0 mb-1">{{ $art->title }}</h1>
        <p class="unterzeile m-0 mb-4">{{ $art->duration }} Minuten{{ $art->text ? ' · '.$art->text : '' }}</p>
    @endif

    @if ($tage->isEmpty())
        <x-leer icon="calendar-xmark" knopf="Ins Gespräch" :href="route('gespraech.index')">Gerade ist keine Zeit frei. Schau bald wieder rein oder schreib mir im Gespräch.</x-leer>
    @else
        <form method="post" action="{{ $booking ? route('buchen.verschieben.store', $booking) : route('buchen.store', $art) }}" class="buchen">
            @csrf
            <h2 class="abschnitt"><i class="fa-regular fa-clock"></i>Wann passt es dir?</h2>
            @foreach ($tage as $tag => $zeiten)
                <details class="karte" @if ($loop->first) open @endif>
                    <summary class="t cursor-pointer">{{ $zeiten->first()->translatedFormat('l, j. F') }} <span class="m" style="display:inline">· {{ $zeiten->count() }} {{ $zeiten->count() === 1 ? 'Zeit' : 'Zeiten' }}</span></summary>
                    <div class="flex flex-wrap gap-2 mt-2.5">
                        @foreach ($zeiten as $z)
                            <label class="zeit-wahl">
                                <input type="radio" name="start" value="{{ $z->getTimestamp() }}" required>
                                <span>{{ $z->format('H:i') }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach

            @if (! $booking && ! empty($art->questions))
                <h2 class="abschnitt"><i class="fa-solid fa-feather"></i>Magst du mir vorher etwas mitgeben?</h2>
                <div class="karte">
                    @foreach ($art->questions as $i => $frage)
                        <label class="block mb-3">
                            <span class="feld-label">{{ $frage }}</span>
                            <textarea name="antworten[{{ $i }}]" rows="3" class="feld" maxlength="3000" placeholder="Schreib oder diktiere ..."></textarea>
                        </label>
                    @endforeach
                    <p class="hinweis m-0">Freiwillig. Das liest nur deine Coachin.</p>
                </div>
            @endif

            <button type="submit" class="knopf knopf-gross" style="width:100%;margin-top:14px"><i class="fa-solid fa-calendar-check"></i>{{ $booking ? 'Auf diese Zeit verschieben' : 'Verbindlich buchen' }}</button>
        </form>
    @endif
</x-layouts.app>

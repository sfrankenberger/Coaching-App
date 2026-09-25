<x-layouts.app :title="$art->title">
    <p style="margin:0 0 8px"><a href="{{ route('buchen.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Termin buchen</a></p>
    <h1 style="margin:0 0 4px">{{ $art->title }}</h1>
    <p class="unterzeile" style="margin:0 0 16px">{{ $art->duration }} Minuten{{ $art->text ? ' · '.$art->text : '' }}</p>

    @if ($tage->isEmpty())
        <x-leer icon="calendar-xmark" knopf="Ins Gespräch" :href="route('gespraech.index')">Gerade ist keine Zeit frei. Schau bald wieder rein oder schreib mir im Gespräch.</x-leer>
    @else
        <form method="post" action="{{ route('buchen.store', $art) }}" class="buchen">
            @csrf
            <h2 class="abschnitt"><i class="fa-regular fa-clock"></i>Wann passt es dir?</h2>
            @foreach ($tage as $tag => $zeiten)
                <details class="karte" @if ($loop->first) open @endif>
                    <summary class="t" style="cursor:pointer">{{ $zeiten->first()->translatedFormat('l, j. F') }} <span class="m" style="display:inline">· {{ $zeiten->count() }} {{ $zeiten->count() === 1 ? 'Zeit' : 'Zeiten' }}</span></summary>
                    <div class="flex flex-wrap gap-2" style="margin-top:10px">
                        @foreach ($zeiten as $z)
                            <label class="zeit-wahl">
                                <input type="radio" name="start" value="{{ $z->getTimestamp() }}" required>
                                <span>{{ $z->format('H:i') }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach

            @if (! empty($art->questions))
                <h2 class="abschnitt"><i class="fa-solid fa-feather"></i>Magst du mir vorher etwas mitgeben?</h2>
                <div class="karte">
                    @foreach ($art->questions as $i => $frage)
                        <label class="block" style="margin-bottom:12px">
                            <span class="feld-label">{{ $frage }}</span>
                            <textarea name="antworten[{{ $i }}]" rows="3" class="feld" maxlength="3000" placeholder="Schreib oder diktiere ..."></textarea>
                        </label>
                    @endforeach
                    <p class="hinweis" style="margin:0">Freiwillig. Das liest nur deine Coachin.</p>
                </div>
            @endif

            <button type="submit" class="knopf knopf-gross" style="width:100%;margin-top:14px"><i class="fa-solid fa-calendar-check"></i>Verbindlich buchen</button>
        </form>
    @endif
</x-layouts.app>

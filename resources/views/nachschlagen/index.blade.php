<x-layouts.app title="Nachschlagen">
    <h1 class="mb-1">Nachschlagen</h1>
    <p class="unterzeile m-0 mb-3.5">Alles an einem Ort: Lektionen, Impulse, Podcast, Material. Ein Wort sucht direkt, ein ganzer Satz fragt nach.</p>

    <div class="segment mb-4" role="tablist">
        <a href="{{ route('nachschlagen.index') }}" @class(['an' => $reiter === 'finden'])>Finden</a>
        <a href="{{ route('nachschlagen.index', ['r' => 'verlauf']) }}" @class(['an' => $reiter === 'verlauf'])>Meine Suchen</a>
        <a href="{{ route('nachschlagen.index', ['r' => 'archiv']) }}" @class(['an' => $reiter === 'archiv'])>Mein Archiv @if ($anzahlGemerkt)<b>{{ $anzahlGemerkt }}</b>@endif</a>
    </div>

    @if ($reiter === 'finden')
        <form method="get" action="{{ route('nachschlagen.index') }}" class="fu-feld" data-nachschlagen data-ziel="#fu-ergebnis">
            <textarea name="q" rows="2" class="feld" placeholder="Ein Wort wie Schneekugel, oder sag, was gerade los ist" aria-label="Wonach suchst du?">{{ $q }}</textarea>
            <div class="fu-tun">
                <button type="submit" class="knopf" data-los>Zeig mir was</button>
                @if ($themen->isNotEmpty())
                    <label class="fu-thema">
                        <span class="sr-only">Oder stöbere nach Thema</span>
                        <select name="thema" class="feld" data-thema>
                            <option value="">Oder nach Thema stöbern</option>
                            @foreach ($themen as $gruppe => $liste)
                                <optgroup label="{{ $gruppe }}">
                                    @foreach ($liste as $t)
                                        <option value="{{ $t->id }}" @selected($thema === $t->id)>{{ $t->name }} ({{ $t->taggables_count }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>
        </form>
        <div id="fu-ergebnis" aria-live="polite">
            @include('nachschlagen._ergebnis')
        </div>
    @elseif ($reiter === 'verlauf')
        @include('nachschlagen._verlauf')
    @else
        @if ($karten->isEmpty())
            <x-leer icon="bookmark">Hier sammelst du, was dir wichtig ist. Tipp beim Suchen auf das Lesezeichen, dann liegt es hier bereit.</x-leer>
        @else
            <div class="fu-liste">
                @foreach ($karten as $k)
                    @include('nachschlagen._karte')
                @endforeach
            </div>
        @endif
    @endif

    @if ($coach)
        @include('nachschlagen._leiste')
    @endif
</x-layouts.app>

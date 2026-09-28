<x-layouts.app title="Nachschlagen">
    <h1 class="mb-1">Nachschlagen</h1>
    <p class="unterzeile m-0 mb-3.5">Alles an einem Ort: Lektionen, Impulse, Podcast, Material. Ein Wort sucht direkt, ein ganzer Satz fragt nach.</p>

    <div class="segment reiter mb-4" role="tablist">
        <a href="{{ route('nachschlagen.index') }}" @class(['an' => $reiter === 'finden'])>Finden</a>
        <a href="{{ route('nachschlagen.index', ['r' => 'themen']) }}" @class(['an' => $reiter === 'themen'])>Themen</a>
        <a href="{{ route('nachschlagen.index', ['r' => 'verlauf']) }}" @class(['an' => $reiter === 'verlauf'])>Meine Suchen</a>
        <a href="{{ route('nachschlagen.index', ['r' => 'archiv']) }}" @class(['an' => $reiter === 'archiv'])>Mein Archiv @if ($anzahlGemerkt)<b>{{ $anzahlGemerkt }}</b>@endif</a>
        @if ($werkzeugeReiter)<a href="{{ route('nachschlagen.index', ['r' => 'werkzeuge']) }}" @class(['an' => $reiter === 'werkzeuge'])>Werkzeuge</a>@endif
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
    @elseif ($reiter === 'themen')
        @if ($themenListe->isEmpty())
            <x-leer icon="bookmark">Noch keine Themen. Sie entstehen, sobald Inhalte zugeordnet sind.</x-leer>
        @else
            <p class="hinweis mb-2">Was beschäftigt dich gerade? Wähle ein Thema, dann findest du alles dazu.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($themenListe as $t)
                    <a href="{{ route('themen.show', $t) }}" class="karte !mb-0 no-underline flex items-center justify-between gap-3">
                        <span>
                            <span class="t">{{ $t->name }}</span>
                            @if ($t->description)<span class="hinweis block">{{ \Illuminate\Support\Str::limit($t->description, 90) }}</span>@endif
                        </span>
                        <span class="chip shrink-0">{{ $t->taggables_count }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @elseif ($reiter === 'werkzeuge')
        @if (! $werkzeugeDarf)
            <x-leer icon="solid:lock" knopf="Mehr dazu" :href="$tuerUrl">Die Werkzeuge gehören zur Coach-Ausbildung. Sobald du dabei bist, findest du sie hier.</x-leer>
        @elseif ($werkzeuge->isEmpty())
            <x-leer icon="solid:hammer">Noch kein Werkzeug da. Das erste kommt bald.</x-leer>
        @else
            <p class="hinweis mb-2">Methoden aus der Coach-Ausbildung: wofür sie da sind, wann sie passen und wie sie gehen.</p>
            @foreach ($werkzeuge as $w)
                <a href="{{ route('werkzeuge.show', $w) }}" class="zeile">
                    <span class="ic"><i class="fa-solid fa-hammer"></i></span>
                    <span class="tx">
                        <b>{{ $w->title }}@if (! $w->is_published) <span class="chip">Entwurf</span>@endif</b>
                        <span style="white-space:normal">{{ \Illuminate\Support\Str::limit($w->purpose, 140) }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-right pf"></i>
                </a>
            @endforeach
        @endif
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

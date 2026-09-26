<x-layouts.app title="Coachees">
    <h1 class="mb-3">Coachees</h1>

    {{-- Auskunft --}}
    <section class="karte" data-auskunft data-url="{{ route('coachees.frage') }}">
        <h3 class="m-0" style="font-size:var(--fs-lg)">Frag mich etwas zu deinem Betrieb</h3>
        <p class="hinweis m-0 mb-2">Buchungen, Termine, Menschen, und wo du was findest.</p>
        <form class="suche m-0" data-auskunft-form>
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <input type="search" name="frage" placeholder="Frag mich: was hat Nicole gebucht, wo trage ich Zeiten ein" aria-label="Frage" autocomplete="off">
            <button type="submit" class="knopf knopf-klein" data-auskunft-los>Fragen</button>
        </form>
        <p class="hinweis mt-2 mb-0 flex flex-wrap gap-1.5 items-center">Zum Beispiel:
            @foreach ($beispiele as $b)<button type="button" class="pille" style="min-height:30px;padding:4px 11px;font-size:var(--fs-xs)" data-auskunft-beispiel>{{ $b }}</button>@endforeach
        </p>
        <div class="mt-3" data-auskunft-antwort aria-live="polite"></div>
    </section>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ $rundnachricht }}" class="knopf knopf-dunkel knopf-klein"><i class="fa-solid fa-bullhorn"></i>Nachricht an mehrere</a>
        <a href="{{ $neuePerson }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-user-plus"></i>Neue Person anlegen</a>
        <a href="/coach" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-table-columns"></i>Coach-Bereich</a>
    </div>

    {{-- Ampel --}}
    <h2 class="abschnitt"><i class="fa-solid fa-traffic-light"></i>Wie stehen sie gerade da<em>{{ $ampel->count() }}</em></h2>
    @forelse ($ampel as $z)
        <article class="karte ampel-karte {{ $z['farbe'] }}">
            <div class="flex items-start gap-2">
                <span class="ampel-punkt"></span>
                <div class="min-w-0 flex-1">
                    <b class="t">{{ $z['user']->name }}</b> <span class="ampel-grund">{{ $z['grund'] }}</span>
                    <span class="hinweis block">{{ $z['aufgaben'][0] }}/{{ $z['aufgaben'][1] }} Aufgaben · {{ $z['verpasst'] ? $z['verpasst'].' Calls verpasst' : 'Calls ok' }} · {{ $z['zuletzt'] ? \App\Support\Zeit::relativ($z['zuletzt']) : 'noch nie da' }}</span>
                </div>
            </div>
            <div class="flex gap-2 mt-2">
                <a href="{{ $z['nachfragen'] }}" class="knopf knopf-leise knopf-klein">kurz nachfragen</a>
                <a href="{{ $z['dossier'] }}" class="knopf knopf-leise knopf-klein">Dossier</a>
            </div>
        </article>
    @empty
        <p class="hinweis">Alles ruhig: niemand wartet, niemand ist lange still.</p>
    @endforelse

    {{-- Freigegeben --}}
    <h2 class="abschnitt"><i class="fa-solid fa-share-nodes"></i>Für dich freigegeben<em>{{ $neues->count() }}</em></h2>
    @forelse ($neues as $n)
        <a href="{{ $n['url'] ?? '#' }}" class="zeile">
            <span class="ic"><i class="fa-solid fa-{{ ['antwort' => 'pen-to-square', 'reflexion' => 'pen-to-square', 'aufgabe' => 'list-check', 'absage' => 'calendar-xmark', 'nachricht' => 'comment'][$n['art']] ?? 'circle' }}"></i></span>
            <span class="tx"><b>{{ $n['wer'] }} {{ $n['was'] }}</b><span>{{ $n['detail'] }} · {{ \App\Support\Zeit::relativ($n['zeit']) }}</span></span>
            <i class="fa-solid fa-chevron-right pf"></i>
        </a>
    @empty
        <p class="hinweis">Nichts Neues geteilt in den letzten Tagen.</p>
    @endforelse

    {{-- Liste --}}
    <p class="mt-5 mb-2 text-md"><b>{{ $begleitet->count() }} in Begleitung</b> · {{ $kontakte->count() }} weitere Kontakte · <b>{{ $wartend }}</b> {{ $wartend === 1 ? 'wartet' : 'warten' }} auf deine Antwort</p>
    <form method="get" class="suche m-0 mb-2">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="{{ $q }}" placeholder="Menschen suchen: Name oder E-Mail" aria-label="Suchen">
        <input type="hidden" name="sort" value="{{ $sort }}">
    </form>
    <div class="flex items-center gap-2 mb-3 flex-wrap">
        <span class="eyebrow">Sortieren</span>
        <span class="segment">
            @foreach (['termin' => 'Nächster Termin', 'aktiv' => 'Zuletzt aktiv', 'name' => 'Name'] as $k => $n)
                <a href="{{ route('coachees.index', array_filter(['sort' => $k, 'q' => $q])) }}" @class(['an' => $sort === $k])>{{ $n }}</a>
            @endforeach
        </span>
    </div>

    @foreach ($begleitet as $z)
        @include('coachees._karte')
    @endforeach
    @if ($begleitet->isEmpty())
        <p class="hinweis">Niemand gefunden.</p>
    @endif

    @if ($kontakte->isNotEmpty())
        <details class="mt-3">
            <summary class="knopf knopf-leise knopf-klein inline-flex" style="cursor:pointer">{{ $kontakte->count() }} weitere Kontakte anzeigen</summary>
            <div class="mt-3">
                @foreach ($kontakte as $z)
                    @include('coachees._karte')
                @endforeach
            </div>
        </details>
    @endif
</x-layouts.app>

<x-layouts.app title="Start" :schmal="true">
    <h1 style="margin:6px 0 12px">Hallo {{ $person->vorname() }}</h1>

    {{-- Diese Woche im Kurs, in der Kursfarbe (wie lea-start2: Woche, Titel, Kurs, Stand der Wochenaufgaben, naechste Aufgabe) --}}
    @if ($weiter->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-graduation-cap"></i>Diese Woche im Kurs</h2>
        @foreach ($weiter as $w)
            @php $p = $w['program']; $step = $w['step']; @endphp
            <a href="{{ route('kurse.schritt', [$p, $step]) }}" class="woche" style="--kc: {{ $p->color ?: '#7C8C9A' }}">
                <span class="bild">
                    @if ($p->cover_url)<img src="{{ $p->cover_url }}" alt="">@else<i class="fa-solid fa-{{ $p->icon ?: 'seedling' }}"></i>@endif
                </span>
                <span class="lab">Woche {{ $w['woche'] }} von {{ $w['wochen'] }}</span>
                <span class="t">{{ $step->title }}</span>
                <span class="k">{{ $p->title }}</span>
                @if ($w['punkte']->isNotEmpty())
                    <span class="punkte">
                        @foreach ($w['punkte']->take(5) as $pt)
                            <span @class(['punkt', 'erledigt' => $pt['erledigt']])><i class="fa-{{ ($pt['ausgelassen'] ?? false) ? 'regular fa-circle-xmark' : 'solid fa-'.($pt['erledigt'] ? 'circle-check' : $pt['icon']) }}"></i>{{ $pt['titel'] }}@if ($pt['ausgelassen'] ?? false) <small>ausgelassen</small>@endif</span>
                        @endforeach
                        @if ($w['punkte']->count() > 5)<span class="punkt"><i class="fa-solid fa-ellipsis"></i>{{ $w['punkte']->count() - 5 }} weitere</span>@endif
                    </span>
                    <span class="reihe">
                        <span class="balken"><span style="width: {{ (int) round($w['erledigt'] / $w['aufgaben'] * 100) }}%"></span></span>
                        <span class="z">{{ $w['erledigt'] }} von {{ $w['aufgaben'] }} erledigt</span>
                    </span>
                @endif
                <span class="cta">Zur Woche &rarr;</span>
            </a>
        @endforeach
    @endif

    {{-- Was ist neu: drei Zeilen, der Rest aufklappbar --}}
    @if ($neues->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-bell"></i>Was ist neu<em>{{ $neues->count() }}</em>
            <span class="rechts">
                <form method="post" action="{{ route('neu.gesehen') }}">@csrf<button type="submit" class="underline text-muted" style="background:none;border:0;cursor:pointer;font:inherit">Alles gesehen</button></form>
            </span>
        </h2>
        <div class="neu-liste">
            @foreach ($neues as $i => $n)
                @if ($i === 3)
                    <details class="neu-mehr"><summary class="knopf knopf-anstoss knopf-breit m-0 mt-1">Weitere {{ $neues->count() - 3 }} anzeigen</summary><div class="neu-liste" style="margin-top:5px">
                @endif
                <a href="{{ $n['url'] }}" class="zeile neu">
                    <span class="ic"><i class="fa-solid fa-{{ $n['icon'] }}"></i></span>
                    <span class="tx">
                        <span class="herkunft">{{ $n['herkunft'] }}</span>
                        <b>{{ $n['titel'] }}</b>
                        <span>{{ $n['zeit']?->translatedFormat('j. F') }}@if ($n['text']) · {{ $n['text'] }}@endif</span>
                    </span>
                    <i class="fa-solid fa-chevron-right pf"></i>
                </a>
                @if ($loop->last && $i >= 3)
                    </div></details>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Naechster Termin --}}
    @if ($termin)
        @php
            $tage = (int) now()->startOfDay()->diffInDays($termin->starts_at->copy()->startOfDay());
            $wann = $termin->isLive() ? 'Jetzt' : match (true) {
                $tage === 0 => 'Heute',
                $tage === 1 => 'Morgen',
                $tage < 7 => 'In '.$tage.' Tagen',
                default => $termin->starts_at->translatedFormat('j. F'),
            };
        @endphp
        <h2 class="abschnitt"><i class="fa-solid fa-calendar"></i>Nächster Termin</h2>
        <div @class(['termin-hero', 'jetzt' => $termin->isLive()])>
            <span class="wann">{{ $wann }}@if (! $termin->all_day) · {{ $termin->starts_at->format('H:i') }} Uhr @endif</span>
            <a href="{{ route('termine.show', $termin) }}" class="t" style="color:inherit;text-decoration:none">{{ $termin->title }}</a>
            <span class="m">{{ $termin->starts_at->translatedFormat('l, j. F') }}{{ $termin->program ? ' · '.$termin->program->title : '' }}</span>
            <div class="knoepfe">
                @if ($termin->isLive() && $termin->zoom_url)
                    <a href="{{ $termin->zoom_url }}" target="_blank" rel="noopener" class="knopf"><i class="fa-solid fa-video"></i>Jetzt beitreten</a>
                @elseif ($termin->type === 'reflection_day')
                    <a href="{{ route('reflexion.index') }}" class="knopf knopf-ruhig"><i class="fa-solid fa-pen-to-square"></i>Reflexion schreiben</a>
                @elseif ($termin->type === 'question_day')
                    <a href="{{ $termin->program ? route('kurse.fragen', [$termin->program, 'frage' => 1]) : route('community', ['frage' => 1]) }}" class="knopf knopf-ruhig"><i class="fa-solid fa-circle-question"></i>Frage stellen</a>
                @elseif ($termin->isOneOnOne())
                    <x-termin-aktionen :event="$termin" :klein="true" class="contents" />
                @else
                    <a href="{{ route('termine.show', $termin) }}" class="knopf knopf-ruhig">Zum Termin</a>
                @endif
            </div>
        </div>
    @endif

    {{-- Offene Aufgaben --}}
    @if ($aufgaben->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-list-check"></i>Offene Aufgaben<em>{{ $offen }}</em>
            @if ($offen > $aufgaben->count())<span class="rechts"><a href="{{ route('aufgaben.index') }}">Alle {{ $offen }} ansehen</a></span>@endif
        </h2>
        @foreach ($aufgaben as $t)
            @include('aufgaben._karte', ['t' => $t, 'ohneKommentare' => true])
        @endforeach
    @endif

    @if ($neues->isEmpty() && ! $termin && $weiter->isEmpty() && $aufgaben->isEmpty())
        <p class="hinweis">Gerade ist nichts offen. Schau in deine Sachen, wenn du zurückblicken magst.</p>
    @endif

</x-layouts.app>

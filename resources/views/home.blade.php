<x-layouts.app title="Start" :schmal="true">
    <h1 style="margin:6px 0 2px">Hallo {{ $person->vorname() }}</h1>
    <p class="unterzeile" style="margin:0 0 6px">{{ now()->translatedFormat('l, j. F') }}</p>

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
                    <details class="neu-mehr"><summary class="knopf knopf-anstoss knopf-breit" style="margin:4px 0 0">Weitere {{ $neues->count() - 3 }} anzeigen</summary><div class="neu-liste" style="margin-top:5px">
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
                    <a href="{{ route('gespraech.index') }}" class="knopf knopf-ruhig"><i class="fa-solid fa-circle-question"></i>Frage stellen</a>
                @else
                    <a href="{{ route('termine.show', $termin) }}" class="knopf knopf-ruhig">Zum Termin</a>
                @endif
            </div>
        </div>
    @endif

    {{-- Diese Woche / weiter im Kurs, in der Kursfarbe --}}
    @foreach ($weiter as $w)
        @php $p = $w['program']; $stand = $w['stand']; $step = $w['step']; @endphp
        <h2 class="abschnitt"><i class="fa-solid fa-graduation-cap"></i>{{ $step ? 'Diese Woche' : 'Mein Kurs' }}</h2>
        <a href="{{ $step ? route('kurse.schritt', [$p, $step]) : ($stand['next'] ? route('kurse.einheit', [$p, $stand['next']]) : route('kurse.show', $p)) }}" class="woche" style="--kc: {{ $p->color ?: '#7C8C9A' }}">
            <span class="bild">
                @if ($p->cover_url)<img src="{{ $p->cover_url }}" alt="">@else<i class="fa-solid fa-{{ $p->icon ?: 'seedling' }}"></i>@endif
            </span>
            <span class="lab">{{ $p->title }}</span>
            <span class="t">{{ $step ? $step->title : ($stand['next']?->title ?? $p->title) }}</span>
            @if ($step && $w['woche'])<span class="k">Woche {{ $w['woche'] }} von {{ $w['wochen'] }}</span>@endif
            <span class="reihe">
                <span class="balken"><span style="width: {{ $stand['percent'] }}%"></span></span>
                <span class="z">{{ $stand['done'] }} von {{ $stand['total'] }} erledigt</span>
            </span>
            @if ($step && $stand['next'])<span class="als">Als Nächstes: {{ $stand['next']->title }}</span>@endif
            <span class="cta">{{ $step ? 'Zur Woche' : ($stand['done'] ? 'Weitermachen' : 'Los geht es') }} &rarr;</span>
        </a>
    @endforeach

    {{-- Offene Aufgaben --}}
    @if ($aufgaben->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-list-check"></i>Offene Aufgaben<em>{{ $offen }}</em>
            @if ($offen > $aufgaben->count())<span class="rechts"><a href="{{ route('aufgaben.index') }}">Alle {{ $offen }} ansehen</a></span>@endif
        </h2>
        @foreach ($aufgaben as $t)
            <div @class(['karte flex items-center gap-3', 'offen' => $t->isOverdue()])>
                <form method="post" action="{{ route('aufgaben.haken', $t) }}" data-haken>
                    @csrf
                    <button type="submit" class="haken" aria-label="Erledigt"><i class="fa-solid fa-check"></i></button>
                </form>
                <a href="{{ route('aufgaben.index') }}" class="min-w-0 flex-1 no-underline">
                    <span class="t">{{ $t->title }}</span>
                    @if ($t->due_at)<span @class(['m', 'text-danger font-semibold' => $t->isOverdue()])>bis {{ $t->due_at->translatedFormat('j. F') }}</span>@endif
                </a>
            </div>
        @endforeach
    @endif

    {{-- Neuester Impuls --}}
    @if ($impuls && $neues->where('herkunft', $impuls->typeLabel())->isEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-lightbulb"></i>Impuls</h2>
        <a href="{{ route('impulse.show', $impuls) }}" class="zeile">
            <span class="ic" style="border-radius:12px;flex-basis:56px;width:56px;height:56px">
                @if ($impuls->image_url)<img src="{{ $impuls->image_url }}" alt="" loading="lazy">@else<i class="fa-solid fa-lightbulb"></i>@endif
            </span>
            <span class="tx"><b>{{ $impuls->title }}</b><span>{{ $impuls->excerptText(90) }}</span></span>
            <i class="fa-solid fa-chevron-right pf"></i>
        </a>
    @endif

    @if ($neues->isEmpty() && ! $termin && $weiter->isEmpty() && $aufgaben->isEmpty())
        <x-leer icon="solid:leaf" knopf="Reflexion schreiben" :href="route('reflexion.index')">Gerade ist nichts offen. Schön, dass du da bist. Magst du kurz festhalten, wie es dir geht?</x-leer>
    @endif

    {{-- Kacheln --}}
    <h2 class="abschnitt"><i class="fa-solid fa-compass"></i>Dein Bereich</h2>
    <div class="kacheln kacheln-2">
        @foreach ([
            ['kurse.index', 'graduation-cap', 'Kurse', 'Wochen, Übungen, Fortschritt'],
            ['termine.index', 'calendar', 'Termine', 'Calls und Aufzeichnungen'],
            ['material.index', 'folder-open', 'Material', 'PDFs, Audios, Links'],
            ['journal.index', 'book-open', 'Mein Journal', 'Aufgaben, Notizen, Reflexion'],
            ['impulse.index', 'lightbulb', 'Impulse', 'Beiträge und Podcast'],
            ['themen.index', 'magnifying-glass', 'Nachschlagen', 'Finde, was dich beschäftigt'],
        ] as [$r, $ic, $t, $x])
            <a href="{{ route($r) }}" class="kachel"><i class="fa-solid fa-{{ $ic }}"></i><span class="tx"><b>{{ $t }}</b><small>{{ $x }}</small></span></a>
        @endforeach
    </div>

    @if ($person->canManageCurrentTenant())
        <h2 class="abschnitt"><i class="fa-solid fa-user-group"></i>Für dich als Coach</h2>
        <div class="karte">
            <p class="x" style="margin:0 0 12px">{{ $personen }} aktive Personen. Kurse, Termine, Material und Impulse pflegst du im Coach-Bereich.</p>
            <a href="/coach" class="knopf">Zum Coach-Bereich</a>
        </div>
    @endif
</x-layouts.app>

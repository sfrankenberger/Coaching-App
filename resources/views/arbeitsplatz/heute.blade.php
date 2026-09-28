<x-layouts.app title="Heute" :schmal="true">
    <h1 style="margin:6px 0 2px">Guten Tag, {{ $person->vorname() }}</h1>
    <p class="unterzeile m-0 mb-3">{{ now()->translatedFormat('l, j. F') }} · {{ $offen === 0 ? 'nichts wartet' : ($offen === 1 ? 'eine Sache wartet' : $offen.' Dinge warten') }}</p>

    @if ($ruhig)
        <p class="hinweis mb-3"><i class="fa-regular fa-face-smile"></i> {{ $ruhig }}</p>
    @endif

    {{-- Wartet auf deine Antwort --}}
    @if ($wartende->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-hourglass-half"></i>Wartet auf deine Antwort<em>{{ $wartende->count() }}</em></h2>
        @foreach ($wartende as $z)
            <article class="karte ampel-karte rot">
                <a href="{{ route('coachees.show', $z['membership']) }}" class="flex items-start gap-2" style="color:inherit;text-decoration:none">
                    <span class="ampel-punkt"></span>
                    <span class="min-w-0 flex-1">
                        <b class="t">{{ $z['user']->name }}</b> <span class="ampel-grund">{{ $z['wann'] }}</span>
                        @if ($z['text'])<span class="hinweis block lesetext">{{ $z['text'] }}</span>@endif
                    </span>
                </a>
                <div class="flex gap-2 mt-2 flex-wrap">
                    <a href="{{ route('coachees.show', $z['membership']) }}" class="knopf knopf-klein"><i class="fa-solid fa-reply"></i>Antworten</a>
                    <form method="post" action="{{ route('coachees.gelesen', $z['membership']) }}">@csrf<button type="submit" class="knopf knopf-leise knopf-klein" title="Als gelesen, ohne Antwort"><i class="fa-solid fa-check"></i>Gelesen</button></form>
                </div>
            </article>
        @endforeach
    @endif

    {{-- Mit dir geteilt --}}
    @if ($geteilt->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-share-nodes"></i>Mit dir geteilt<em>{{ $geteilt->count() }}</em></h2>
        @foreach ($geteilt as $n)
            <a href="{{ $n['url'] ?? '#' }}" class="zeile">
                <span class="ic"><i class="fa-solid fa-{{ ['antwort' => 'pen-to-square', 'reflexion' => 'pen-to-square', 'aufgabe' => 'list-check'][$n['art']] ?? 'circle' }}"></i></span>
                <span class="tx"><b>{{ $n['wer'] }} {{ $n['was'] }}</b><span>{{ $n['detail'] }} · {{ \App\Support\Zeit::relativ($n['zeit']) }}</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    @endif

    {{-- Fragen ohne Antwort --}}
    @if ($fragen->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-regular fa-circle-question"></i>Fragen ohne Antwort<em>{{ $fragen->count() }}</em></h2>
        @foreach ($fragen as $f)
            <a href="{{ route('fragen.show', $f) }}" class="zeile">
                <span class="ic"><i class="fa-regular fa-circle-question"></i></span>
                <span class="tx"><b>{{ \Illuminate\Support\Str::limit($f->title, 70) }}</b><span>{{ $f->user?->name }}{{ $f->program ? ' · '.$f->program->title : '' }} · {{ \App\Support\Zeit::relativ($f->created_at) }}</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    @endif

    {{-- Wartet auf Freigabe --}}
    @if ($freigaben->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-film"></i>Wartet auf Freigabe<em>{{ $freigaben->count() }}</em></h2>
        @foreach ($freigaben as $e)
            <a href="/coach/events/{{ $e->id }}/edit" class="zeile">
                <span class="ic"><i class="fa-solid fa-film"></i></span>
                <span class="tx"><b>{{ $e->title }}</b><span>{{ \App\Support\Zeit::wannKurz($e->starts_at) }} · Aufzeichnung und Zusammenfassung sind da, noch nicht verschickt</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    @endif

    {{-- Als Naechstes --}}
    <h2 class="abschnitt"><i class="fa-solid fa-calendar"></i>Als Nächstes<em>{{ $termine->count() }}</em>
        <span class="rechts"><a href="{{ route('termine.index') }}">Alle Termine</a></span>
    </h2>
    @if ($termine->isNotEmpty())
        @php $t = $termine->first(); @endphp
        <div @class(['termin-hero', 'jetzt' => $t->isLive()])>
            <span class="wann">{{ $t->isLive() ? 'Jetzt' : \App\Support\Zeit::wann($t->starts_at) }}</span>
            <a href="{{ route('termine.show', $t) }}" class="t" style="color:inherit;text-decoration:none">{{ $t->title }}</a>
            <span class="m">{{ $t->user ? '1:1 mit '.$t->user->name : ($t->program?->title ?? 'Alle') }}{{ $t->ends_at ? ' · bis '.$t->ends_at->format('H:i') : '' }}</span>
            <div class="knoepfe">
                @if ($t->zoom_url)<a href="{{ $t->zoom_url }}" target="_blank" rel="noopener" class="knopf"><i class="fa-solid fa-video"></i>{{ $t->isLive() ? 'Jetzt beitreten' : 'Zoom öffnen' }}</a>@endif
                @if ($t->user && ($mt = \App\Models\Membership::where('user_id', $t->user_id)->first()))<a href="{{ route('coachees.show', [$mt, 'r' => 'vorbereitung']) }}" class="knopf knopf-ruhig"><i class="fa-solid fa-wand-magic-sparkles"></i>Vorbereiten</a>@endif
            </div>
        </div>
        @foreach ($termine->slice(1) as $e)
            <a href="{{ route('termine.show', $e) }}" class="zeile">
                <span class="ic"><i class="fa-regular fa-calendar"></i></span>
                <span class="tx"><b>{{ $e->title }}</b><span>{{ \App\Support\Zeit::wann($e->starts_at) }}{{ $e->user ? ' · 1:1 mit '.$e->user->name : ($e->program ? ' · '.$e->program->title : '') }}</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    @else
        <p class="hinweis">In den nächsten acht Tagen steht nichts an. <a href="/coach/events/create">Termin planen</a></p>
    @endif

    {{-- Schnell hin --}}
    <h2 class="abschnitt"><i class="fa-solid fa-bolt"></i>Schnell hin</h2>
    <div class="kacheln kacheln-2">
        <a href="{{ route('coachees.index') }}#neu" class="kachel"><i class="fa-solid fa-user-plus"></i><span class="tx"><b>Neue Person</b><small>anlegen, einladen</small></span></a>
        <a href="{{ route('filament.coach.pages.rundnachricht') }}" class="kachel"><i class="fa-solid fa-bullhorn"></i><span class="tx"><b>Nachricht an mehrere</b><small>Kurs oder Auswahl</small></span></a>
        <a href="{{ route('assistent') }}" class="kachel"><i class="fa-solid fa-wand-magic-sparkles"></i><span class="tx"><b>Assistent</b><small>fragen, merken</small></span></a>
        <a href="/coach" class="kachel"><i class="fa-solid fa-sliders"></i><span class="tx"><b>Verwaltung</b><small>Kurse, Termine, Angebote</small></span></a>
    </div>
</x-layouts.app>

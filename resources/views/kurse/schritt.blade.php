<x-layouts.app :title="$schritt->title">
    @php $ich = auth()->user(); @endphp
    <div style="--kc: {{ $program->color ?: '#7C8C9A' }}">
        <div class="flex items-center gap-3 m-0 mb-1.5">
            <a href="{{ route('kurse.show', $program) }}" class="knopf knopf-ruhig knopf-quadrat" aria-label="Zurück zum Kurs"><i class="fa-solid fa-chevron-left"></i></a>
            <span class="min-w-0 flex-1">
                <span class="eyebrow block">Kurs</span>
                <span class="hinweis block truncate">{{ $program->title }}</span>
            </span>
            @if ($vorher)
                <a href="{{ route('kurse.schritt', [$program, $vorher]) }}" class="knopf knopf-rund" aria-label="Schritt davor"><i class="fa-solid fa-chevron-left"></i></a>
            @endif
            @if ($nachher && ($nachher->isUnlocked($program) || $ich->canManageCurrentTenant()))
                <a href="{{ route('kurse.schritt', [$program, $nachher]) }}" class="knopf knopf-rund" aria-label="Schritt danach"><i class="fa-solid fa-chevron-right"></i></a>
            @endif
        </div>

        {{-- Wochenband zum Wischen (wie lea-woche): Nummer, Haken, Schloss, "Jetzt" --}}
        @if ($band->count() > 1)
            <div class="wochenband" data-wochenband>
                @foreach ($band as $b)
                    @if ($b['offen'])
                        <a href="{{ route('kurse.schritt', [$program, $b['step']]) }}" @class(['wb', 'hier' => $b['hier'], 'jetzt' => $b['jetzt'], 'fertig' => $b['fertig']]) @if ($b['hier']) aria-current="page" @endif>
                            <span class="n">@if ($b['fertig'])<i class="fa-solid fa-check"></i>@else{{ $b['nummer'] }}@endif</span>
                            <span class="t">{{ $program->pacing === 'weekly' ? 'Woche '.$b['nummer'] : \Illuminate\Support\Str::limit($b['step']->title, 22) }}@if ($program->pacing === 'weekly' && $b['step']->unlocks_at) <small>{{ $b['step']->unlocks_at->translatedFormat('j.n.') }}</small>@endif</span>
                            @if ($b['jetzt'])<span class="j">Jetzt</span>@endif
                        </a>
                    @else
                        <span class="wb zu"><span class="n"><i class="fa-solid fa-lock"></i></span><span class="t">{{ $program->pacing === 'weekly' ? 'Woche '.$b['nummer'] : \Illuminate\Support\Str::limit($b['step']->title, 22) }}@if ($program->pacing === 'weekly' && $b['step']->unlocks_at) <small>{{ $b['step']->unlocks_at->translatedFormat('j.n.') }}</small>@endif</span></span>
                    @endif
                @endforeach
            </div>
            @if ($aktuell && $aktuell->id !== $schritt->id)
                <p class="m-0 mt-1"><a href="{{ route('kurse.schritt', [$program, $aktuell]) }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-location-arrow"></i>Zur aktuellen Woche</a></p>
            @endif
        @endif

        <p class="eyebrow" style="color:var(--c-primary);margin:18px 0 4px">{{ $program->pacing === 'weekly' ? 'Woche' : 'Schritt' }} {{ $schritt->week_number ?? $nummer }} von {{ $anzahl }}@if ($aktuell?->id === $schritt->id) · diese Woche @endif</p>
        <h1 class="m-0 mb-1">{{ $schritt->title }}</h1>
        @if ($schritt->unlocks_at && $program->pacing === 'weekly')
            <p class="unterzeile m-0 mb-3">ab {{ $schritt->unlocks_at->translatedFormat('l, j. F') }}</p>
        @endif
        @if ($schritt->summary)
            <div class="karte"><div class="prose-app">{!! $schritt->summary !!}</div></div>
        @endif

        {{-- Calls dieser Woche: vorher Zoom, danach Aufzeichnung --}}
        @foreach ($termine->reject(fn ($t) => in_array($t->type, \App\Models\Event::ALL_DAY_TYPES, true)) as $t)
            <x-termin-karte class="mt-3" :termin="$t" />
        @endforeach

        {{-- Lektionen der Woche: nur wenn es welche gibt, nicht jede Woche hat Material zum Anschauen --}}
        @if ($units->isNotEmpty())
            <h2 class="abschnitt"><i class="fa-solid fa-circle-play"></i>{{ $program->pacing === 'weekly' ? 'Diese Woche' : 'Lektionen' }}<em>{{ $units->count() }}</em></h2>
            <div class="modul einzeln">
                @foreach ($units as $unit)
                    @include('kurse._einheit-zeile', ['unit' => $unit, 'erledigt' => $done->contains($unit->id)])
                @endforeach
            </div>
        @elseif ($program->pacing !== 'weekly')
            <h2 class="abschnitt"><i class="fa-solid fa-circle-play"></i>Lektionen</h2>
            <div class="leer"><i class="fa-regular fa-hourglass"></i>Hier kommt noch etwas. Schau bald wieder rein.</div>
        @endif

        {{-- Aufgaben der Woche: von der Coachin und eigene --}}
        <h2 class="abschnitt"><i class="fa-solid fa-list-check"></i>Deine Aufgaben diese Woche @if ($aufgaben->whereNull('done_at')->count())<em>{{ $aufgaben->whereNull('done_at')->count() }}</em>@endif</h2>
        @foreach ($aufgaben as $t)
            @include('aufgaben._karte', ['t' => $t])
        @endforeach
        @if ($rueckstand->isNotEmpty())
            <details class="mt-2">
                <summary class="hinweis cursor-pointer"><i class="fa-solid fa-clock-rotate-left"></i> Aus früheren Wochen noch offen: {{ $rueckstand->count() }}</summary>
                @foreach ($rueckstand as $t)
                    @include('aufgaben._karte', ['t' => $t, 'ohneKommentare' => true])
                @endforeach
            </details>
        @endif
        @unless ($ich->canManageCurrentTenant())
            <form method="post" action="{{ route('aufgaben.store') }}" class="baustein mt-1">
                @csrf
                <input type="hidden" name="program_id" value="{{ $program->id }}">
                <input type="hidden" name="step_id" value="{{ $schritt->id }}">
                <input type="hidden" name="visibility" value="coach">
                <input type="hidden" name="zurueck" value="{{ url()->current() }}">
                <label for="vorhaben" class="eyebrow block m-0 mb-2">Was nimmst du dir diese Woche vor?</label>
                <div class="flex gap-2">
                    <input id="vorhaben" name="title" class="feld" maxlength="160" required placeholder="Ein kleiner, konkreter Schritt">
                    <button type="submit" class="knopf" aria-label="Aufgabe anlegen" style="flex:none"><i class="fa-solid fa-plus"></i></button>
                </div>
                <p class="hinweis m-0 mt-2">Deine Coachin sieht, was du dir vornimmst. Du kannst es im Journal ändern.</p>
            </form>
        @endunless

        {{-- Material der Woche und ihrer Lektionen --}}
        @if ($material->isNotEmpty())
            <h2 class="abschnitt"><i class="fa-solid fa-folder-open"></i>Material<em>{{ $material->count() }}</em></h2>
            @foreach ($material as $r)
                @include('kurse._material', ['r' => $r])
            @endforeach
        @endif

        {{-- Reflexions- und Fragentag: Teilnehmerinnen haben sie als Aufgaben oben, das Team sieht die Tage hier --}}
        @foreach ($ich->canManageCurrentTenant() ? $termine->filter(fn ($t) => in_array($t->type, \App\Models\Event::ALL_DAY_TYPES, true)) : collect() as $t)
            @php $refl = $t->type === 'reflection_day'; $geschrieben = $refl && $reflexion; $gefragt = ! $refl && $fragen->isNotEmpty(); @endphp
            <a href="{{ $refl ? ($geschrieben ? route('reflexion.index').'#reflexion-'.$reflexion->id : route('reflexion.index')) : route('kurse.fragen', [$program, 'frage' => 1]) }}" class="zeile" style="margin-top:12px">
                <span class="ic" @if ($geschrieben || $gefragt) style="background:var(--c-success-soft);color:var(--c-success)" @endif><i class="fa-solid fa-{{ $geschrieben || $gefragt ? 'circle-check' : ($refl ? 'pen-to-square' : 'circle-question') }}"></i></span>
                <span class="tx"><b>{{ $refl ? ($geschrieben ? 'Reflexion geschrieben' : 'Reflexion schreiben') : ($gefragt ? 'Noch eine Frage stellen' : 'Frage stellen') }}</b><span>{{ $t->typeLabel() }} · {{ $t->starts_at->translatedFormat('l, j. F') }}@if ($geschrieben) · {{ \App\Support\Zeit::wannKurz($reflexion->created_at) }}@endif</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach

        {{-- Die eigene Reflexion dieser Woche --}}
        @if ($reflexion)
            <details class="karte mt-2">
                <summary class="t cursor-pointer"><i class="fa-solid fa-pen-to-square" style="color:var(--c-primary)"></i> Deine Reflexion dieser Woche <span class="hinweis">· {{ $reflexion->week_label ?: $reflexion->created_at->translatedFormat('j. F') }}</span></summary>
                @foreach (\App\Http\Controllers\ReflexionController::FRAGEN as $k => [$ico, $frage])
                    @if ($reflexion->$k)<p class="mt-2 mb-0"><b class="block text-md">{{ $ico }} {{ $frage }}</b><span class="lesetext whitespace-pre-line">{{ $reflexion->$k }}</span></p>@endif
                @endforeach
                <p class="m-0 mt-3"><a href="{{ route('reflexion.index', ['refl' => $reflexion->id]) }}" class="knopf knopf-leise knopf-klein">Weiterschreiben</a></p>
            </details>
        @endif

        {{-- Die eigenen Fragen dieser Woche --}}
        @if ($fragen->isNotEmpty())
            <h2 class="abschnitt"><i class="fa-solid fa-circle-question"></i>Deine Fragen diese Woche<em>{{ $fragen->count() }}</em></h2>
            @foreach ($fragen as $f)
                <a href="{{ route('fragen.show', $f) }}" class="zeile">
                    <span class="ic"><i class="fa-solid fa-{{ $f->isOffen() ? 'circle-question' : 'circle-check' }}"></i></span>
                    <span class="tx"><b>{{ $f->title }}</b><span>{{ $f->statusLabel() }} · {{ $f->answers_count }} {{ $f->answers_count === 1 ? 'Antwort' : 'Antworten' }}</span></span>
                    <i class="fa-solid fa-chevron-right pf"></i>
                </a>
            @endforeach
        @endif
    </div>
</x-layouts.app>

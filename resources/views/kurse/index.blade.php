<x-layouts.app title="Meine Kurse">
    <h1>Meine Kurse</h1>

    @php
        // Gliederung nach Zugangsart (wie lea-meine-kurse): laufende Programme, Selbstlernen, Arbeitsbuecher, Club
        $gruppen = $programs->groupBy(fn ($p) => $p->isWorkbook() ? 'buch' : ($p->type === 'club' ? 'club' : (($p->settings['gratis'] ?? false) ? 'gratis' : ($p->pacing === 'weekly' ? 'laufend' : 'selbst'))));
        $titel = ['laufend' => ['graduation-cap', 'Dein Programm'], 'selbst' => ['circle-play', 'Selbstlernkurse'], 'club' => ['mug-hot', 'Im Club enthalten'], 'gratis' => ['gift', 'Für alle offen'], 'buch' => ['book', 'Arbeitsbücher']];
        $abschnitte = $gruppen->count() > 1 || $gesperrt->isNotEmpty() || $bald->isNotEmpty();
    @endphp

    @forelse (array_keys($titel) as $g)
        @continue(! $gruppen->has($g))
        @if ($abschnitte)
            <h2 class="abschnitt"><i class="fa-solid fa-{{ $titel[$g][0] }}"></i>{{ $titel[$g][1] }}<em>{{ $gruppen[$g]->count() }}</em></h2>
        @endif
        @foreach ($gruppen[$g] as $program)
            @php $stand = $program->stand; $call = $program->call; $icon = $program->icon ?: ($g === 'buch' ? 'book-open' : 'seedling'); $ende = $bis->get($program->id); @endphp
            <a href="{{ route('kurse.show', $program) }}" class="karte kurs-karte" style="--kc: {{ $program->color ?: '#7C8C9A' }}">
                @if ($program->cover_url)
                    <span class="kurs-bild" style="background-image:url('{{ $program->cover_url }}')"></span>
                @else
                    <span class="kurs-bild kurs-bild-farbe"><i class="fa-solid fa-{{ $icon }}"></i></span>
                @endif
                <span class="kurs-text">
                    <span class="eyebrow">{{ $program->typeLabel() }}@if ($ende) · Freigeschaltet bis {{ $ende->translatedFormat('j. F Y') }}@endif</span>
                    <span class="kurs-titel">{{ $program->title }}</span>
                    @if ($team && (! $program->is_published || $program->is_internal))
                        {{-- nur fuer das Team: Teilnehmerinnen sehen Unveroeffentlichtes gar nicht --}}
                        <span class="chip {{ $program->is_published ? '' : 'chip-warn' }} mt-1" style="align-self:flex-start"><i class="fa-solid fa-{{ $program->is_published ? 'user-lock' : 'eye-slash' }}"></i>{{ $program->is_published ? 'Nur intern' : 'Nicht veröffentlicht' }}</span>
                    @endif
                    @if ($program->subtitle)<span class="x block mt-1">{{ \Illuminate\Support\Str::limit($program->subtitle, 110) }}</span>@endif
                    @if ($call)
                        <span class="chip mt-2" style="align-self:flex-start"><i class="fa-solid fa-video"></i>{{ $call->isLive() ? 'Call läuft gerade' : 'Nächster Call: '.$call->starts_at->translatedFormat('D, j. M, H:i').' Uhr' }}</span>
                    @endif
                    @if ($stand['total'])
                        <span class="flex items-center gap-3 mt-3">
                            <span class="balken"><span style="width: {{ $stand['percent'] }}%"></span></span>
                            <span class="balken-label">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
                        </span>
                    @endif
                </span>
            </a>
        @endforeach
    @empty
        @if ($gesperrt->isEmpty() && $bald->isEmpty())
            <x-leer icon="solid:graduation-cap" knopf="Sag mir Bescheid" :href="route('gespraech.index', ['entwurf' => 'Hallo '.$coach.', ich interessiere mich für einen Kurs. Sag mir bitte Bescheid, wenn etwas für mich dabei ist. '])">Noch kein Kurs für dich freigeschaltet. Sobald es losgeht, siehst du ihn hier. Bis dahin findest du bei den Impulsen etwas zum Lesen und Hören.</x-leer>
        @endif
    @endforelse

    {{-- Schaufenster: was es noch gibt (gesperrt mit Preis und Kauflink) und was bald kommt --}}
    @if ($gesperrt->isNotEmpty() || $bald->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-lock"></i>Was es noch gibt<em>{{ $gesperrt->count() + $bald->count() }}</em></h2>
        @foreach ($gesperrt as $o)
            @php $p = $o->programs->first(); $preise = $o->preise(); @endphp
            <a href="{{ $o->kaufUrl('kurse') }}" class="karte kurs-karte gesperrt" style="--kc: {{ $p?->color ?: '#7C8C9A' }}">
                @if ($p?->cover_url)
                    <span class="kurs-bild" style="background-image:url('{{ $p->cover_url }}')"><i class="fa-solid fa-lock schloss"></i></span>
                @else
                    <span class="kurs-bild kurs-bild-farbe"><i class="fa-solid fa-lock"></i></span>
                @endif
                <span class="kurs-text">
                    <span class="eyebrow">{{ $o->programs->count() > 1 ? $o->programs->count().' Kurse' : ($p?->typeLabel() ?? 'Angebot') }}</span>
                    <span class="kurs-titel">{{ $o->title }}</span>
                    @if ($o->subtitle ?? $p?->subtitle)<span class="x block mt-1">{{ \Illuminate\Support\Str::limit($o->subtitle ?? $p?->subtitle, 110) }}</span>@endif
                    <span class="flex flex-wrap items-center gap-2 mt-2">
                        @if ($o->is_free)<span class="chip chip-gut">Kostenlos</span>@else @foreach ($preise as $w => $b)<span class="chip">{{ $o->preisMitIntervall($b, $w) }}</span>@endforeach @endif
                        <span class="hinweis">Freischalten &rarr;</span>
                    </span>
                </span>
            </a>
        @endforeach
        @foreach ($bald as $p)
            <span class="karte kurs-karte gesperrt bald" style="--kc: {{ $p->color ?: '#7C8C9A' }}">
                @if ($p->cover_url)
                    <span class="kurs-bild" style="background-image:url('{{ $p->cover_url }}')"></span>
                @else
                    <span class="kurs-bild kurs-bild-farbe"><i class="fa-solid fa-{{ $p->icon ?: 'hourglass-half' }}"></i></span>
                @endif
                <span class="kurs-text">
                    <span class="eyebrow">{{ $p->typeLabel() }}@if ($p->starts_at) · ab {{ $p->starts_at->translatedFormat('j. F Y') }}@endif</span>
                    <span class="kurs-titel">{{ $p->title }}</span>
                    @if ($p->subtitle)<span class="x block mt-1">{{ \Illuminate\Support\Str::limit($p->subtitle, 110) }}</span>@endif
                    <span class="chip mt-2" style="align-self:flex-start"><i class="fa-regular fa-clock"></i>Kommt bald</span>
                </span>
            </span>
        @endforeach
    @endif
</x-layouts.app>

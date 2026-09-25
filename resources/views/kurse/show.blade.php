<x-layouts.app :title="$program->title">
    <div style="--kc: {{ $program->color ?: '#7C8C9A' }}">
        <p style="margin:0 0 8px"><a href="{{ route('kurse.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Meine Kurse</a></p>

        <div class="bildband" @if ($program->cover_url) style="background-image:url('{{ $program->cover_url }}')" @endif>
            <div>
                <span class="eyebrow" style="color:rgba(255,255,255,.8)">{{ $program->typeLabel() }}</span>
                <h1>{{ $program->title }}</h1>
                @if ($program->subtitle)<p>{{ $program->subtitle }}</p>@endif
            </div>
        </div>

        @if ($stand['total'])
            <div class="karte">
                <div class="flex items-center gap-3">
                    <span class="x" style="flex:none"><b style="font-family:var(--font-heading);font-size:20px;font-weight:400;color:var(--c-text)">{{ $stand['done'] }}</b> von {{ $stand['total'] }} {{ $program->steps->isEmpty() ? 'erledigt' : 'Schritten erledigt' }}</span>
                </div>
                <span class="balken" style="display:block;margin:10px 0 14px"><span style="width: {{ $stand['percent'] }}%"></span></span>
                @if ($stand['next'])
                    <a href="{{ route('kurse.einheit', [$program, $stand['next']]) }}" class="knopf">{{ $stand['done'] > 0 ? 'Weitermachen' : 'Los geht es' }} <i class="fa-solid fa-arrow-right"></i></a>
                @else
                    <span class="chip chip-gut"><i class="fa-solid fa-check"></i>Alles erledigt</span>
                @endif
            </div>
        @endif

        @if ($program->description)
            <details class="karte">
                <summary class="t" style="cursor:pointer">Worum es geht</summary>
                <div class="prose-app" style="margin-top:10px">{!! $program->description !!}</div>
            </details>
        @endif

        @if ($freigabeOffen)
            <div class="baustein">
                <p class="eyebrow" style="margin:0 0 8px">Bevor du anfängst</p>
                <p class="karte-titel">Wer liest mit?</p>
                <p class="x" style="margin:0 0 12px">Alles, was du hier schreibst, ist zuerst nur für dich. Du kannst deine Antworten mit deiner Coachin teilen, damit sie vor eurem nächsten Gespräch weiss, wo du stehst. Du entscheidest das einmal jetzt und kannst es jederzeit ändern.</p>
                <form method="post" action="{{ route('kurse.freigabe', $program) }}" class="flex flex-wrap gap-2">
                    @csrf
                    <button type="submit" name="modus" value="alles" class="knopf"><i class="fa-solid fa-lock-open"></i>Alles teilen</button>
                    <button type="submit" name="modus" value="einzeln" class="knopf knopf-ruhig">Ich entscheide je Übung</button>
                </form>
            </div>
        @endif

        @if ($kontingent)
            @php $hallo = $coach === 'deine Coachin' ? 'Hallo, ' : 'Hallo '.$coach.', '; @endphp
            <div class="karte">
                <span class="eyebrow"><i class="fa-solid fa-ticket"></i> Deine Sitzungen</span>
                <div class="flex items-baseline gap-2" style="margin-top:4px">
                    <b style="font-family:var(--font-heading);font-size:26px;font-weight:400">{{ $kontingent['offen'] }}</b>
                    <span class="x">von {{ $kontingent['gesamt'] }} noch offen</span>
                </div>
                <span class="balken" style="display:block;margin:10px 0 6px"><span style="width: {{ round(($kontingent['gehabt'] + $kontingent['geplant']) / $kontingent['gesamt'] * 100) }}%"></span></span>
                <p class="hinweis" style="margin:0 0 12px">{{ $kontingent['gehabt'] }} gehabt{{ $kontingent['geplant'] ? ', '.$kontingent['geplant'].' geplant' : '' }}</p>
                <a href="{{ route('gespraech.index', ['entwurf' => $hallo.($kontingent['offen'] ? 'ich hätte gern einen nächsten Termin. Mir passt es am besten ' : 'meine Sitzungen sind aufgebraucht. Ich hätte gern weitere. ')]) }}" class="knopf knopf-klein">
                    <i class="fa-regular fa-calendar-plus"></i>{{ $kontingent['offen'] ? 'Termin anfragen' : 'Weitere Sitzungen anfragen' }}
                </a>
            </div>
        @endif

        @if ($naechsterCall)
            <x-termin-karte :termin="$naechsterCall" :status="false" />
        @endif

        @if ($infos->isNotEmpty())
            <h2 class="abschnitt"><i class="fa-solid fa-bullhorn"></i>Infos von {{ $coach }}</h2>
            @foreach ($infos as $p)
                <a href="{{ route('impulse.show', $p) }}" class="zeile">
                    <span class="ic"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="tx"><b>{{ $p->title }}</b><span>{{ $p->published_at?->translatedFormat('j. F') }} · {{ $p->excerptText(60) }}</span></span>
                    <i class="fa-solid fa-chevron-right pf"></i>
                </a>
            @endforeach
        @endif

        @if ($program->isGroup() && ! $program->isWorkbook())
            <a href="{{ route('kurse.fragen', $program) }}" class="zeile">
                <span class="ic"><i class="fa-solid fa-circle-question"></i></span>
                <span class="tx"><b>Fragen an {{ $coach }}</b><span>{{ $fragen ? $fragen.' offen' : 'Frag, was dich beschäftigt' }}</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
            <a href="{{ route('kurse.austausch', $program) }}" class="zeile">
                <span class="ic"><i class="fa-solid fa-user-group"></i></span>
                <span class="tx"><b>Austausch in der Gruppe</b><span>Fragen an alle, Erfahrungen teilen</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endif

        @if ($program->pacing === 'none' || $program->steps->isEmpty())
            <div class="modul einzeln">
                @foreach ($program->units->where('is_published', true) as $unit)
                    @include('kurse._einheit-zeile', ['unit' => $unit, 'erledigt' => $done->contains($unit->id)])
                @endforeach
            </div>
        @elseif ($program->pacing === 'weekly')
            <h2 class="abschnitt"><i class="fa-solid fa-calendar-week"></i>Alle Wochen</h2>
            @foreach ($program->steps as $step)
                @php
                    $offen = $step->isUnlocked($program) || auth()->user()->canManageCurrentTenant();
                    $units = $step->units->where('is_published', true);
                    $fertig = $units->filter(fn ($u) => $done->contains($u->id))->count();
                    $aktuell = $aktuellerSchritt?->id === $step->id;
                    $alle = $units->count() && $fertig === $units->count();
                @endphp
                <a @if ($offen) href="{{ route('kurse.schritt', [$program, $step]) }}" @endif @class(['karte woche-zeile', 'heute' => $aktuell, 'fertig' => ! $offen])>
                    <span @class(['woche-nr', 'fertig' => $alle, 'jetzt' => $aktuell && ! $alle])>{{ $step->week_number ?? $loop->iteration }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="t">{{ $step->title }}</span>
                        <span class="m">
                            @if ($step->unlocks_at){{ $offen ? 'ab ' : 'frei ab ' }}{{ $step->unlocks_at->translatedFormat('j. F') }}@endif
                            @if ($units->count()) · {{ $fertig }} von {{ $units->count() }} erledigt @endif
                        </span>
                    </span>
                    @if ($aktuell)<span class="chip chip-coach">Jetzt dran</span>@endif
                    <i class="fa-solid fa-{{ $offen ? 'chevron-right' : 'lock' }}" style="color:var(--c-ghost);font-size:13px"></i>
                </a>
            @endforeach
        @else
            @foreach ($program->steps as $step)
                @php
                    $offen = $step->isUnlocked($program) || auth()->user()->canManageCurrentTenant();
                    $units = $step->units->where('is_published', true);
                    $fertig = $units->filter(fn ($u) => $done->contains($u->id))->count();
                @endphp
                <section @class(['modul', 'zu' => ! $offen]) id="schritt-{{ $step->id }}">
                    <a class="modul-kopf" @if ($offen) href="{{ route('kurse.schritt', [$program, $step]) }}" @endif>
                        <span class="nr">{{ str_pad((string) ($step->week_number ?? $loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="name">{{ $step->title }}</span>
                        <span class="stand">@if ($offen){{ $fertig }}/{{ $units->count() }}@else<i class="fa-solid fa-lock"></i> {{ $step->unlocks_at?->translatedFormat('j. F') }}@endif</span>
                    </a>
                    @if ($offen)
                        @foreach ($units as $unit)
                            @include('kurse._einheit-zeile', ['unit' => $unit, 'erledigt' => $done->contains($unit->id)])
                        @endforeach
                    @endif
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.app>

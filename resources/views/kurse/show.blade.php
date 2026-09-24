<x-layouts.app :title="$program->title">
    <p class="mb-2"><a href="{{ route('kurse.index') }}" class="hinweis no-underline">&larr; Meine Kurse</a></p>

    <x-karte style="--kc: {{ $program->color ?: 'var(--c-primary)' }}">
        <span class="hinweis uppercase tracking-wider text-xs font-semibold">{{ $program->typeLabel() }}</span>
        <h1>{{ $program->title }}</h1>
        @if ($program->subtitle)
            <p class="text-ink-soft mt-1">{{ $program->subtitle }}</p>
        @endif
        @if ($program->description)
            <div class="prose-app mt-3">{!! $program->description !!}</div>
        @endif

        @if ($stand['total'])
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[180px]">
                    <div class="text-md"><b>{{ $stand['done'] }}</b> von {{ $stand['total'] }} erledigt</div>
                    <span class="mt-1 block h-1.5 rounded-full bg-line overflow-hidden"><span class="block h-full rounded-full" style="width: {{ $stand['percent'] }}%; background: var(--kc)"></span></span>
                </div>
                @if ($stand['next'])
                    <a href="{{ route('kurse.einheit', [$program, $stand['next']]) }}" class="knopf">{{ $stand['done'] > 0 ? 'Weiter machen' : 'Loslegen' }}</a>
                @endif
            </div>
        @endif
    </x-karte>

    @if ($program->isGroup() && ! $program->isWorkbook())
        <a href="{{ route('kurse.austausch', $program) }}" class="karte flex items-center gap-3 no-underline text-ink hover:border-primary">
            <span class="size-9 shrink-0 rounded-full bg-page grid place-items-center text-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M4 5h16v11H8l-4 4z"/></svg></span>
            <span class="flex-1"><span class="block text-base">Austausch in der Gruppe</span><span class="hinweis">Fragen an alle, Erfahrungen teilen</span></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><path d="m9 6 6 6-6 6"/></svg>
        </a>
    @endif

    @if ($freigabeOffen)
        <x-karte titel="Bevor du anfängst: Wer liest mit?">
            <p class="text-ink-soft mb-3">Alles, was du hier schreibst, ist zuerst nur für dich. Du kannst deine Antworten mit deiner Coachin teilen, damit sie vor eurem nächsten Gespräch weiss, wo du stehst. Du entscheidest das einmal jetzt und kannst es jederzeit ändern.</p>
            <form method="post" action="{{ route('kurse.freigabe', $program) }}" class="eingabe-knoepfe" style="justify-content:flex-start">
                @csrf
                <button type="submit" name="modus" value="alles" class="knopf">Alles teilen</button>
                <button type="submit" name="modus" value="einzeln" class="knopf knopf-leise">Ich entscheide je Übung</button>
            </form>
        </x-karte>
    @endif

    @if ($program->pacing === 'none' || $program->steps->isEmpty())
        @foreach ($program->units->where('is_published', true) as $unit)
            @include('kurse._einheit-zeile', ['unit' => $unit, 'erledigt' => $done->contains($unit->id)])
        @endforeach
    @else
        <h2 class="mt-4 mb-2">{{ $program->pacing === 'weekly' ? 'Alle Wochen' : 'Kursinhalt' }}</h2>
        @foreach ($program->steps as $step)
            @php
                $offen = $step->isUnlocked($program) || auth()->user()->canManageCurrentTenant();
                $units = $step->units->where('is_published', true);
                $fertig = $units->filter(fn ($u) => $done->contains($u->id))->count();
                $aktuell = $aktuellerSchritt?->id === $step->id;
            @endphp
            <a @if ($offen) href="{{ route('kurse.schritt', [$program, $step]) }}" @endif
               @class(['karte flex items-center gap-3 no-underline text-ink', 'hover:border-primary' => $offen, 'opacity-60' => ! $offen, 'border-primary' => $aktuell])>
                <span class="size-9 shrink-0 rounded-full border border-line grid place-items-center font-heading text-md {{ $units->count() && $fertig === $units->count() ? 'bg-success text-primary-contrast border-success' : '' }}">
                    {{ $step->week_number ?? $loop->iteration }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-base leading-snug">{{ $step->title }}</span>
                    <span class="hinweis block">
                        @if ($program->pacing === 'weekly' && $step->unlocks_at)
                            {{ $offen ? 'ab ' : 'frei ab ' }}{{ $step->unlocks_at->translatedFormat('j. F') }}
                            @if ($units->count()) · @endif
                        @endif
                        @if ($units->count()) {{ $fertig }} von {{ $units->count() }} erledigt @endif
                        @if ($aktuell) · <b class="text-primary">Jetzt dran</b> @endif
                    </span>
                </span>
                @if (! $offen)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><path d="m9 6 6 6-6 6"/></svg>
                @endif
            </a>
        @endforeach
    @endif
</x-layouts.app>

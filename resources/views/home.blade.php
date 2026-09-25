<x-layouts.app title="Start">
    <h1 class="mb-1">Hallo {{ $person->vorname() }}</h1>
    <p class="text-ink-soft mb-3">{{ now()->translatedFormat('l, j. F') }}{{ $offen ? ' · '.$offen.' offene '.($offen === 1 ? 'Aufgabe' : 'Aufgaben') : '' }}{{ $ungelesen ? ' · '.$ungelesen.' ungelesen' : '' }}</p>

    @if ($neues->isNotEmpty())
        <x-karte titel="Neu für dich">
            <ul class="divide-y divide-line">
                @foreach ($neues as $n)
                    <li class="py-2">
                        <span class="block text-base leading-snug">{{ $n['titel'] }}</span>
                        @if ($n['text'])<span class="hinweis">{{ $n['text'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-karte>
    @endif

    @foreach ($weiter as $w)
        @php $p = $w['program']; $stand = $w['stand']; $step = $w['step']; @endphp
        <x-karte style="--kc: {{ $p->color ?: 'var(--c-primary)' }}">
            <span class="hinweis uppercase tracking-wider text-xs font-semibold">{{ $step ? 'Diese Woche' : 'Weiter im Kurs' }} · {{ $p->title }}</span>
            <h2 class="mt-1">{{ $step ? $step->title : ($stand['next']?->title ?? $p->title) }}</h2>
            @if ($step && $step->summary)
                <div class="prose-app mt-1">{!! \Illuminate\Support\Str::limit(strip_tags($step->summary), 200) !!}</div>
            @endif
            <div class="mt-3 flex items-center gap-3">
                <span class="h-1.5 flex-1 rounded-full bg-line overflow-hidden"><span class="block h-full rounded-full" style="width: {{ $stand['percent'] }}%; background: var(--kc)"></span></span>
                <span class="hinweis whitespace-nowrap">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($step)
                    <a href="{{ route('kurse.schritt', [$p, $step]) }}" class="knopf">Zur Woche</a>
                @endif
                @if ($stand['next'])
                    <a href="{{ route('kurse.einheit', [$p, $stand['next']]) }}" class="knopf {{ $step ? 'knopf-leise' : '' }}">{{ $stand['done'] ? 'Weiter: ' : 'Anfangen: ' }}{{ \Illuminate\Support\Str::limit($stand['next']->title, 40) }}</a>
                @endif
            </div>
        </x-karte>
    @endforeach

    @if ($termin)
        <x-karte>
            <span class="hinweis uppercase tracking-wider text-xs font-semibold">Nächster Termin</span>
            <h2 class="mt-1"><a href="{{ route('termine.show', $termin) }}" class="no-underline text-ink">{{ $termin->title }}</a></h2>
            <p class="text-ink-soft mt-1">{{ $termin->starts_at->translatedFormat('l, j. F') }}@if (! $termin->all_day), {{ $termin->starts_at->format('H:i') }} Uhr @endif{{ $termin->program ? ' · '.$termin->program->title : '' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($termin->isLive() && $termin->zoom_url)
                    <a href="{{ $termin->zoom_url }}" target="_blank" rel="noopener" class="knopf">Jetzt beitreten</a>
                @endif
                <a href="{{ route('termine.show', $termin) }}" class="knopf knopf-leise">Zum Termin</a>
            </div>
        </x-karte>
    @endif

    @if ($aufgaben->isNotEmpty())
        <x-karte titel="Deine Aufgaben">
            <ul class="divide-y divide-line">
                @foreach ($aufgaben as $t)
                    <li class="flex items-center gap-3 py-2">
                        <form method="post" action="{{ route('aufgaben.haken', $t) }}" data-haken>
                            @csrf
                            <button type="submit" class="size-7 rounded-lg border border-line bg-page grid place-items-center" aria-label="Erledigt"></button>
                        </form>
                        <span class="min-w-0 flex-1">
                            <span class="block text-base leading-snug">{{ $t->title }}</span>
                            @if ($t->due_at)<span class="hinweis {{ $t->isOverdue() ? 'text-danger font-semibold' : '' }}">bis {{ $t->due_at->translatedFormat('j. F') }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2"><a href="{{ route('aufgaben.index') }}" class="hinweis">Alle Aufgaben</a></p>
        </x-karte>
    @endif

    @if ($impuls)
        <article class="karte flex items-center gap-3">
            @if ($impuls->image_url)
                <a href="{{ route('impulse.show', $impuls) }}" class="shrink-0"><img src="{{ $impuls->image_url }}" alt="" class="size-16 rounded-lg object-cover bg-page" loading="lazy"></a>
            @endif
            <div class="min-w-0 flex-1">
                <span class="hinweis uppercase tracking-wider text-xs font-semibold">{{ $impuls->typeLabel() }}</span>
                <a href="{{ route('impulse.show', $impuls) }}" class="block text-base leading-snug no-underline text-ink">{{ $impuls->title }}</a>
                <p class="text-md text-ink-soft mt-1">{{ $impuls->excerptText(120) }}</p>
            </div>
        </article>
    @endif

    <div class="grid grid-cols-2 gap-2 my-2">
        @foreach ([['kurse.index', 'Kurse', 'Wochen, Übungen, Fortschritt'], ['termine.index', 'Termine', 'Calls und Aufzeichnungen'], ['material.index', 'Material', 'PDFs, Audios, Links'], ['journal.index', 'Journal', 'Aufgaben, Notizen, Reflexion'], ['impulse.index', 'Impulse', 'Beiträge und Podcast'], ['themen.index', 'Themen', 'Finde, was dich gerade beschäftigt'], ['merkliste', 'Merkliste', 'Was du dir gemerkt hast'], ['gespraech.index', 'Gespräch', $ungelesen ? $ungelesen.' ungelesen' : 'Der direkte Draht']] as [$r, $t, $x])
            <a href="{{ route($r) }}" class="karte !mt-0 no-underline text-ink hover:border-primary">
                <span class="block text-base font-semibold">{{ $t }}</span>
                <span class="hinweis">{{ $x }}</span>
            </a>
        @endforeach
    </div>

    @if ($person->canManageCurrentTenant())
        <x-karte titel="Für dich als Coach">
            <p class="text-ink-soft mb-3">{{ $personen }} aktive Personen. Kurse, Termine, Material und Impulse pflegst du im Coach-Bereich.</p>
            <a href="/coach" class="knopf">Zum Coach-Bereich</a>
        </x-karte>
    @endif
</x-layouts.app>

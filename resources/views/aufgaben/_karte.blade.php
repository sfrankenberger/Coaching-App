@php $tage = ['mo' => 'M', 'di' => 'D', 'mi' => 'M', 'do' => 'D', 'fr' => 'F', 'sa' => 'S', 'so' => 'S']; $done = $t->daysDone(); @endphp
<article id="aufgabe-{{ $t->id }}" @class(['karte', 'opacity-70' => $t->isDone(), 'border-primary' => $t->is_pinned])>
    <div class="flex items-start gap-3">
        <form method="post" action="{{ route('aufgaben.haken', $t) }}" data-haken>
            @csrf
            <button type="submit" @class(['mt-0.5 size-7 rounded-lg border grid place-items-center', 'bg-primary border-primary text-primary-contrast' => $t->isDone(), 'border-line bg-page' => ! $t->isDone()]) aria-label="Erledigt">
                @if ($t->isDone())<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="size-4"><path d="m5 12 5 5L20 7"/></svg>@endif
            </button>
        </form>
        <div class="min-w-0 flex-1">
            <span @class(['block text-base leading-snug', 'line-through text-muted' => $t->isDone()])>{{ $t->title }}</span>
            <span class="hinweis block">
                @if ($t->assigned_by && $t->assigner) Von {{ $t->assigner->vorname() }} · @endif
                @if ($t->due_at) <span @class(['text-danger font-semibold' => $t->isOverdue()])>bis {{ $t->due_at->translatedFormat('j. F') }}{{ $t->due_time ? ', '.$t->due_time.' Uhr' : '' }}</span> · @endif
                @if ($t->program) {{ $t->program->title }} · @endif
                {{ \App\Models\Note::VISIBILITIES[$t->visibility] ?? '' }}
            </span>
            @if ($t->body)<p class="text-md text-ink-soft mt-1 whitespace-pre-line">{{ $t->body }}</p>@endif
            @if ($t->is_daily && ! $t->isDone())
                <div class="mt-2 flex flex-wrap items-center gap-1" data-tage="{{ route('aufgaben.tag', $t) }}">
                    <span class="hinweis w-full">Diese Woche <b>{{ count($done) }} von 7</b></span>
                    @foreach ($tage as $k => $l)
                        <form method="post" action="{{ route('aufgaben.tag', $t) }}" class="inline">@csrf<input type="hidden" name="tag" value="{{ $k }}"><button @class(['size-7 rounded-md border text-xs', 'bg-primary text-primary-contrast border-primary' => in_array($k, $done, true), 'border-line bg-page' => ! in_array($k, $done, true)]) aria-label="{{ $k }}">{{ $l }}</button></form>
                    @endforeach
                </div>
            @endif
        </div>
        <details class="relative shrink-0">
            <summary class="list-none cursor-pointer size-8 grid place-items-center rounded-full hover:bg-page" aria-label="Mehr">···</summary>
            <div class="absolute right-0 z-10 mt-1 w-44 rounded-xl border border-line bg-card p-1 shadow">
                <a href="{{ route('aufgaben.index', ['bearbeiten' => $t->id]) }}" class="block rounded-lg px-3 py-2 text-md no-underline text-ink hover:bg-page">Bearbeiten</a>
                <form method="post" action="{{ route('aufgaben.destroy', $t) }}" onsubmit="return confirm('Aufgabe löschen?')">@csrf @method('DELETE')<button class="block w-full rounded-lg px-3 py-2 text-left text-md text-danger hover:bg-page">Löschen</button></form>
            </div>
        </details>
    </div>
</article>

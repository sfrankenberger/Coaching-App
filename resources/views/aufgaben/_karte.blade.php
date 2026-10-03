@php $tage = ['mo' => 'M', 'di' => 'D', 'mi' => 'M', 'do' => 'D', 'fr' => 'F', 'sa' => 'S', 'so' => 'S']; $done = $t->daysDone(); @endphp
<article id="aufgabe-{{ $t->id }}" @class(['karte', 'fertig' => $t->isDone(), 'ausgelassen' => $t->isSkipped(), 'heute' => $t->is_pinned && $t->isOffen(), 'offen' => $t->isOverdue()])>
    <div class="flex items-start gap-3">
        <form method="post" action="{{ route('aufgaben.haken', $t) }}" data-haken>
            @csrf
            <button type="submit" @class(['haken', 'an' => $t->isDone()]) style="margin-top:1px" aria-label="Erledigt"><i class="fa-solid fa-check"></i></button>
        </form>
        <div class="min-w-0 flex-1">
            <span class="t">@if ($t->is_pinned)<i class="fa-solid fa-thumbtack" style="color:var(--c-primary);font-size:12px;margin-right:6px"></i>@endif{{ $t->title }}</span>
            <span class="m">
                @if ($t->assigned_by && $t->assigner) Von {{ $t->assigner->vorname() }} · @endif
                @if ($t->due_at && ! $t->isDone() && $t->due_at->isToday()) <span class="font-semibold" style="color:var(--c-primary)">heute dran{{ $t->due_time ? ', '.$t->due_time.' Uhr' : '' }}</span> ·
                @elseif ($t->due_at) <span @class(['text-danger font-semibold' => $t->isOverdue()])>{{ $t->weekday ? \App\Models\Task::WEEKDAYS[$t->weekday].', ' : 'bis ' }}{{ $t->due_at->translatedFormat('j. F') }}{{ $t->due_time ? ', '.$t->due_time.' Uhr' : '' }}</span> · @endif
                @if ($t->program) {{ $t->program->title }} · @endif
                @if ($t->unit_id && $t->program && $t->unit) <a href="{{ route('kurse.einheit', [$t->program, $t->unit]) }}">zur Übung</a> · @endif
                {{ \App\Models\Note::sichtbarkeitText($t->visibility) }}
                @if ($t->project_id && $t->projekt) · <x-projekt-chip :projekt="$t->projekt" /> @endif
            </span>
            @if ($t->body)<p class="lesetext mt-1 whitespace-pre-line text-md">{{ $t->body }}</p>@endif
            <x-anhaenge :item="$t" />
            @if ($t->isSkipped())
                <span class="hinweis block mt-1"><i class="fa-regular fa-circle-xmark"></i> Ausgelassen{{ $t->skipped_at ? ' am '.$t->skipped_at->translatedFormat('j. F') : '' }}.
                    @if ($t->user_id === auth()->id())<form method="post" action="{{ route('aufgaben.auslassen', $t) }}" class="inline">@csrf<button type="submit" class="underline" style="background:none;border:0;cursor:pointer;font:inherit;color:inherit">Doch machen</button></form>@endif
                </span>
            @elseif ($t->user_id === auth()->id() && $t->isOffen())
                <span class="flex flex-wrap items-center gap-2 mt-2">
                    @if ($aktion = app(\App\Programs\Wochenaufgabe::class)->aktion($t))
                        <a href="{{ $aktion['url'] }}" class="knopf knopf-klein"><i class="fa-solid fa-{{ $aktion['icon'] }}"></i>{{ $aktion['text'] }}</a>
                    @endif
                    @if ($t->assigned_by || $t->source !== 'manual')
                        <form method="post" action="{{ route('aufgaben.auslassen', $t) }}" class="inline">@csrf<button type="submit" class="knopf knopf-leise knopf-klein"><i class="fa-regular fa-circle-xmark"></i>{{ $t->auslassenText() }}</button></form>
                    @endif
                </span>
            @endif
            @if ($t->is_daily && $t->isOffen())
                <div class="mt-2 flex flex-wrap items-center gap-1" data-tage="{{ route('aufgaben.tag', $t) }}">
                    <span class="hinweis w-full">Diese Woche <b>{{ count($done) }} von 7</b></span>
                    @foreach ($tage as $k => $l)
                        <form method="post" action="{{ route('aufgaben.tag', $t) }}" class="inline">@csrf<input type="hidden" name="tag" value="{{ $k }}"><button @class(['size-7 rounded-md border text-xs', 'bg-primary text-primary-contrast border-primary' => in_array($k, $done, true), 'border-line bg-page' => ! in_array($k, $done, true)]) aria-label="{{ $k }}">{{ $l }}</button></form>
                    @endforeach
                </div>
            @endif
            @unless (($ohneKommentare ?? false))<x-kommentare :item="$t" />@endunless
        </div>
        <x-element-menue :item="$t" typ="task" :bearbeiten="route('aufgaben.index', ['bearbeiten' => $t->id])" :loeschen="route('aufgaben.destroy', $t)" frage="Aufgabe löschen?" />
    </div>
</article>

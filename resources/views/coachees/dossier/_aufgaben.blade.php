<details class="karte mb-3" @if ($errors->has('title')) open @endif>
    <summary class="cursor-pointer" style="font-weight:600"><i class="fa-solid fa-plus"></i> Aufgabe geben</summary>
    <form method="post" action="{{ route('coachees.aufgabe', $m) }}" class="mt-2">
        @csrf
        <label class="feld-label">Aufgabe<input type="text" name="title" class="feld" required maxlength="200" placeholder="Was soll {{ $person->vorname() }} tun?"></label>
        <label class="feld-label">Dazu<textarea name="body" rows="2" class="feld" placeholder="Ein Satz dazu, wenn nötig"></textarea></label>
        <label class="feld-label">Bis<input type="date" name="due_at" class="feld"></label>
        <button type="submit" class="knopf knopf-klein">Geben, {{ $person->vorname() }} bekommt Bescheid</button>
    </form>
</details>

@forelse ($aufgaben as $t)
    <div class="karte" id="aufgabe-{{ $t->id }}">
        <div class="flex items-start gap-2">
            <i class="fa-{{ $t->done_at ? 'solid fa-circle-check' : 'regular fa-circle' }} mt-1" style="color:var(--c-{{ $t->done_at ? 'success' : 'ghost' }})"></i>
            <div class="min-w-0 flex-1">
                <b class="t" @if ($t->done_at) style="text-decoration:line-through;opacity:.7" @endif>{{ $t->title }}</b>
                <span class="hinweis block">{{ $t->assigned_by ? 'von dir' : 'eigene' }}{{ $t->due_at ? ' · bis '.\App\Support\Zeit::datum($t->due_at) : '' }}{{ $t->done_at ? ' · erledigt '.\App\Support\Zeit::relativ($t->done_at) : '' }}</span>
                @if ($t->body)<p class="lesetext text-md mt-1 mb-0 whitespace-pre-line">{{ $t->body }}</p>@endif
                @if (in_array($t->kind, ['reflexion', 'frage'], true))
                    {{-- Steht hinter dem Haken ein Text? Reflexion im Reiter Geteilt (wenn geteilt), Frage auf ihrer Seite --}}
                    @php $g = app(\App\Programs\Wochenaufgabe::class)->geschrieben($t); @endphp
                    <span class="hinweis block mt-1">
                        @if ($g instanceof \App\Models\Reflection)
                            <i class="fa-solid fa-pen-to-square"></i> Reflexion geschrieben {{ \App\Support\Zeit::relativ($g->created_at) }}:
                            @if ($g->visibility !== 'private')<a href="{{ route('coachees.show', [$m, 'r' => 'geteilt']) }}">lesen unter Geteilt</a>@else nur für {{ $person->vorname() }} sichtbar @endif
                        @elseif ($g instanceof \App\Models\Question)
                            <i class="fa-solid fa-circle-question"></i> Frage gestellt {{ \App\Support\Zeit::relativ($g->created_at) }}: <a href="{{ route('fragen.show', $g) }}">{{ \Illuminate\Support\Str::limit($g->title ?: $g->body, 60) }}</a>
                        @elseif ($t->done_at)
                            <i class="fa-regular fa-circle-check"></i> Abgehakt, ohne {{ $t->kind === 'reflexion' ? 'Reflexion' : 'Frage' }} zu schreiben.
                        @elseif ($t->skipped_at)
                            <i class="fa-regular fa-circle-xmark"></i> Diese Woche ausgelassen.
                        @endif
                    </span>
                @endif
            </div>
        </div>
        @include('coachees.dossier._kommentar', ['item' => $t, 'typ' => 'task'])
    </div>
@empty
    <p class="hinweis">Noch keine Aufgaben.</p>
@endforelse
@if ($privateAufgaben)
    <p class="hinweis">Dazu {{ $privateAufgaben }} private {{ $privateAufgaben === 1 ? 'Aufgabe' : 'Aufgaben' }}, die nur {{ $person->vorname() }} sieht.</p>
@endif

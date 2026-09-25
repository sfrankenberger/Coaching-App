<x-layouts.app :title="$frage->title">
    @php $ich = auth()->user(); $verwaltet = $ich->canManageCurrentTenant(); $meinWunsch = $callWunsch->contains('user_id', $ich->id); @endphp
    <div style="--kc: {{ $frage->program?->color ?: '#7C8C9A' }}">
        <p style="margin:0 0 8px"><a href="{{ route('kurse.fragen', $frage->program) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Fragen · {{ $frage->program->title }}</a></p>

        <article class="karte">
            <span class="flex flex-wrap items-center gap-2" style="margin-bottom:8px">
                <span @class(['chip', 'chip-coach' => $frage->status === 'call', 'chip-gut' => in_array($frage->status, ['beantwortet', 'besprochen'], true)])>{{ $frage->statusLabel() }}</span>
                @if ($frage->visibility === 'coach')<span class="chip"><i class="fa-solid fa-lock"></i>Nur {{ $coach }}</span>@endif
            </span>
            <h1 style="margin:0 0 6px;font-size:var(--fs-2xl)">{{ $frage->title }}</h1>
            <p class="m" style="margin:0 0 10px">{{ $frage->user?->name }} · {{ $frage->created_at->translatedFormat('j. F Y, H:i') }}</p>
            @if ($frage->body)<p class="x whitespace-pre-line" style="margin:0;font-size:var(--fs-base)">{{ $frage->body }}</p>@endif

            <div class="flex flex-wrap items-center gap-2" style="margin-top:14px">
                <form method="post" action="{{ route('fragen.call', $frage) }}">
                    @csrf
                    <button type="submit" @class(['reaktion', 'an' => $meinWunsch]) title="Bitte im Call besprechen"><i class="fa-solid fa-bullseye" style="color:var(--c-primary)"></i> Bitte im Call besprechen @if ($callWunsch->count())<span class="z">{{ $callWunsch->count() }}</span>@endif</button>
                </form>
                @if ($verwaltet)
                    <form method="post" action="{{ route('fragen.status', $frage) }}" class="flex items-center gap-2">
                        @csrf
                        <select name="status" class="pille" onchange="this.form.submit()" aria-label="Status">
                            @foreach (\App\Models\Question::STATUS as $k => $l)<option value="{{ $k }}" @selected($frage->status === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </form>
                @endif
                @if ($frage->user_id === $ich->id || $verwaltet)
                    <form method="post" action="{{ route('fragen.destroy', $frage) }}" onsubmit="return confirm('Frage mit allen Antworten löschen?')" style="margin-left:auto">
                        @csrf @method('DELETE')
                        <button type="submit" class="knopf knopf-text knopf-klein"><i class="fa-solid fa-trash"></i>Löschen</button>
                    </form>
                @endif
            </div>
        </article>

        <h2 class="abschnitt"><i class="fa-solid fa-comments"></i>Antworten<em>{{ $frage->answers->count() }}</em></h2>
        @foreach ($frage->answers as $a)
            @php $vomTeam = $team->contains($a->user_id); @endphp
            <article id="antwort-{{ $a->id }}" @class(['karte', 'neu' => $vomTeam])>
                <span class="flex items-center gap-2" style="margin-bottom:4px">
                    <span class="t" style="font-size:var(--fs-md)">{{ $a->user?->name }}</span>
                    @if ($vomTeam)<span class="chip chip-coach">Team</span>@endif
                    <span class="m" style="margin-left:auto">{{ $a->created_at->translatedFormat('j. M, H:i') }}</span>
                </span>
                <p class="x whitespace-pre-line" style="margin:0;font-size:var(--fs-base)">{{ $a->body }}</p>
                @if ($a->user_id === $ich->id || $verwaltet)
                    <form method="post" action="{{ route('fragen.antwort.loeschen', $a) }}" onsubmit="return confirm('Antwort löschen?')" style="text-align:right">
                        @csrf @method('DELETE')
                        <button type="submit" class="knopf knopf-text knopf-klein" style="padding:4px 0">Löschen</button>
                    </form>
                @endif
            </article>
        @endforeach
        @if ($frage->answers->isEmpty())
            <div class="leer" style="padding:14px"><i class="fa-regular fa-comment"></i>Noch keine Antwort.</div>
        @endif

        <form method="post" action="{{ route('fragen.antworten', $frage) }}" class="baustein eingabe" style="margin-top:12px">
            @csrf
            <label for="antwort" class="eyebrow">Deine Antwort</label>
            <textarea id="antwort" name="body" class="feld" rows="3" required placeholder="Schreib oder diktiere ..."></textarea>
            <div class="eingabe-knoepfe"><button type="submit" class="knopf"><i class="fa-solid fa-paper-plane"></i>Antworten</button></div>
        </form>
    </div>
</x-layouts.app>

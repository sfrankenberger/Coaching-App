<x-layouts.app :title="$frage->title">
    @php $ich = auth()->user(); $verwaltet = $ich->canManageCurrentTenant(); $meinWunsch = $callWunsch->contains('user_id', $ich->id); $lang = \App\Support\Textform::lang($frage->body); @endphp
    <div style="--kc: {{ $frage->program?->color ?: '#7C8C9A' }}" data-frage="{{ $frage->id }}" data-frage-neu="{{ route('fragen.neu', $frage) }}" data-letzte="{{ (int) $frage->answers->max('id') }}">
        <p class="m-0 mb-2"><a href="{{ route('kurse.fragen', $frage->program) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Fragen · {{ $frage->program->title }}</a></p>

        <article class="karte">
            <span class="flex flex-wrap items-center gap-2 mb-2">
                <span @class(['chip', 'chip-coach' => $frage->status === 'call', 'chip-gut' => in_array($frage->status, ['beantwortet', 'besprochen'], true)])>{{ $frage->statusLabel() }}</span>
                @if ($frage->visibility === 'coach')<span class="chip"><i class="fa-solid fa-lock"></i>Nur {{ $coach }}</span>@endif
                @if ($folgen === true)<span class="chip"><i class="fa-solid fa-bell"></i>Du folgst</span>@elseif ($folgen === false)<span class="chip"><i class="fa-solid fa-bell-slash"></i>Stumm</span>@endif
            </span>
            <h1 style="margin:0 0 6px;font-size:var(--fs-2xl)">{{ $frage->title }}</h1>
            <p class="m m-0 mb-2.5 flex items-center gap-2"><x-avatar :user="$frage->user" :size="24" />{{ $frage->user?->name }} · {{ $frage->created_at->translatedFormat('j. F Y, H:i') }}</p>
            @if ($frage->body)
                <div @class(['x lesetext m-0 text-base', 'weiterlesen' => $lang]) @if ($lang) data-weiterlesen @endif>{!! \App\Support\Textform::lebendig($frage->body) !!}</div>
                @if ($lang)<button type="button" class="knopf knopf-text knopf-klein" data-weiterlesen-knopf>Weiterlesen</button>@endif
            @endif
            <x-anhaenge :item="$frage" />

            <x-reaktionen :item="$frage" typ="question" :nur="\App\Models\Question::REAKTIONEN" />

            <div class="flex flex-wrap items-center gap-2 mt-3">
                <form method="post" action="{{ route('fragen.call', $frage) }}">
                    @csrf
                    <button type="submit" @class(['reaktion', 'an' => $meinWunsch]) title="Bitte im Call besprechen"><i class="fa-solid fa-bullseye" style="color:var(--c-primary)"></i> Bitte im Call besprechen @if ($callWunsch->count())<span class="z">{{ $callWunsch->count() }}</span>@endif</button>
                </form>
                <form method="post" action="{{ route('fragen.folgen', $frage) }}">
                    @csrf
                    @if ($folgen === null)
                        <input type="hidden" name="was" value="{{ $frage->user_id === $ich->id ? 'stumm' : 'folgen' }}">
                        <button type="submit" class="reaktion" title="{{ $frage->user_id === $ich->id ? 'Keine Meldungen mehr zu dieser Frage' : 'Jede Antwort mitbekommen' }}"><i class="fa-solid fa-{{ $frage->user_id === $ich->id ? 'bell-slash' : 'bell' }}"></i> {{ $frage->user_id === $ich->id ? 'Stumm' : 'Folgen' }}</button>
                    @else
                        <input type="hidden" name="was" value="{{ $folgen ? 'stumm' : 'normal' }}">
                        <button type="submit" class="reaktion an"><i class="fa-solid fa-{{ $folgen ? 'bell' : 'bell-slash' }}"></i> {{ $folgen ? 'Nicht mehr folgen' : 'Wieder melden' }}</button>
                    @endif
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
                    <form class="ml-auto" method="post" action="{{ route('fragen.destroy', $frage) }}" onsubmit="return confirm('Frage mit allen Antworten löschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="knopf knopf-text knopf-klein"><i class="fa-solid fa-trash"></i>Löschen</button>
                    </form>
                @endif
            </div>
        </article>

        <h2 class="abschnitt"><i class="fa-solid fa-comments"></i>Antworten<em>{{ $frage->answers->count() }}</em>
            @if ($frage->answers->count() > 1)
                <span class="rechts">
                    @foreach (['alt' => 'Älteste', 'neu' => 'Neueste', 'herz' => 'Beliebteste'] as $k => $l)
                        <a href="{{ route('fragen.show', [$frage, 'sort' => $k]) }}" @class(['pille', 'an' => $sort === $k])>{{ $l }}</a>
                    @endforeach
                </span>
            @endif
        </h2>
        <div data-antworten>
            @foreach ($antworten as $a)
                @include('fragen._antwort', ['a' => $a, 'frage' => $frage, 'team' => $team, 'seit' => $seit])
            @endforeach
        </div>
        <button type="button" class="knopf knopf-leise knopf-breit" data-neue-antworten hidden></button>
        @if ($frage->answers->isEmpty())
            <div class="leer" style="padding:14px"><i class="fa-regular fa-comment"></i>Noch keine Antwort.</div>
        @endif

        @if ($frage->istZu())
            <p class="hinweis mt-3"><i class="fa-solid fa-lock"></i> Diese Frage ist abgeschlossen. Neue Antworten gibt es hier nicht mehr.</p>
        @else
            <form method="post" action="{{ route('fragen.antworten', $frage) }}" class="baustein eingabe mt-3" id="antworten" data-entwurf="antwort-{{ $frage->id }}">
                @csrf
                <input type="hidden" name="parent_id" value="" data-parent>
                <p class="hinweis m-0" data-antwort-auf-hinweis hidden>Antwort auf <b data-antwort-auf-name></b> <button type="button" class="knopf knopf-text knopf-klein" data-antwort-auf-weg>abbrechen</button></p>
                <label for="antwort" class="eyebrow">Deine Antwort</label>
                <textarea id="antwort" name="body" class="feld" rows="3" required placeholder="Schreib oder diktiere ... Mit @Name sprichst du jemanden direkt an." data-erwaehnen='@json($erwaehnbar)'></textarea>
                <div class="eingabe-knoepfe"><button type="submit" class="knopf"><i class="fa-solid fa-paper-plane"></i>Antworten</button></div>
            </form>
        @endif
    </div>
</x-layouts.app>

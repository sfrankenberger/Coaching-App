<x-layouts.app :title="'Fragen · '.$program->title">
    <div style="--kc: {{ $program->color ?: '#7C8C9A' }}">
        <p class="m-0 mb-2"><a href="{{ route('kurse.show', $program) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> {{ $program->title }}</a></p>
        <h1 class="mb-1">Fragen an {{ $coach }}</h1>
        <p class="unterzeile m-0 mb-3.5">Was dich beschäftigt, hilft oft auch den anderen. {{ $coach }} beantwortet die Fragen hier oder nimmt sie in den nächsten Call.</p>

        <details class="baustein" @if ($errors->any()) open @endif>
            <summary class="knopf knopf-anstoss cursor-pointer"><i class="fa-solid fa-circle-question"></i>Frage stellen</summary>
            <form method="post" action="{{ route('kurse.fragen.store', $program) }}" class="eingabe mt-3.5" data-entwurf="frage-{{ $program->id }}">
                @csrf
                <div>
                    <label for="frage-titel" class="feld-label">Deine Frage in einem Satz</label>
                    <input id="frage-titel" name="title" class="feld" maxlength="200" required value="{{ old('title') }}">
                    @error('title')<p class="fehler mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="frage-text" class="feld-label">Mehr dazu, freiwillig</label>
                    <textarea id="frage-text" name="body" class="feld" rows="4" placeholder="Was ist passiert, was hast du schon versucht?">{{ old('body') }}</textarea>
                </div>
                <div>
                    <span class="feld-label">Wer sieht die Frage?</span>
                    <label class="flex items-center gap-2 text-md" style="margin:4px 0"><input type="radio" name="visibility" value="program" checked class="accent-primary"> Alle im Kurs</label>
                    <label class="flex items-center gap-2 text-md"><input type="radio" name="visibility" value="coach" class="accent-primary"> Nur {{ $coach }}</label>
                </div>
                <div class="eingabe-knoepfe"><button type="submit" class="knopf"><i class="fa-solid fa-paper-plane"></i>Frage stellen</button></div>
            </form>
        </details>

        <div class="pillen">
            @foreach (['' => 'Alle', 'offen' => 'Offen', 'call' => 'Für den Call', 'erledigt' => 'Beantwortet'] as $k => $l)
                <a href="{{ route('kurse.fragen', array_filter([$program, 'f' => $k])) }}" @class(['pille', 'an' => $filter === $k])>{{ $l }}</a>
            @endforeach
        </div>

        @forelse ($fragen as $f)
            <a href="{{ route('fragen.show', $f) }}" @class(['karte', 'block no-underline', 'neu' => $f->status === 'offen'])>
                <span class="flex flex-wrap items-center gap-2 mb-1.5">
                    <span @class(['chip', 'chip-coach' => $f->status === 'call', 'chip-gut' => in_array($f->status, ['beantwortet', 'besprochen'], true)])>{{ $f->statusLabel() }}</span>
                    @if ($f->visibility === 'coach')<span class="chip"><i class="fa-solid fa-lock"></i>Nur {{ $coach }}</span>@endif
                </span>
                <span class="t">{{ $f->title }}</span>
                <span class="m">
                    {{ $f->user?->vorname() }} · {{ $f->created_at->translatedFormat('j. F') }}
                    · {{ $f->answers_count }} {{ $f->answers_count === 1 ? 'Antwort' : 'Antworten' }}
                    @if ($f->call_wuensche) · <i class="fa-solid fa-bullseye"></i> {{ $f->call_wuensche }}× für den Call gewünscht @endif
                </span>
            </a>
        @empty
            <x-leer icon="circle-question">{{ $filter ? 'Keine Fragen in dieser Auswahl.' : 'Noch keine Fragen. Mach gern den Anfang, es gibt keine dummen.' }}</x-leer>
        @endforelse
    </div>
</x-layouts.app>

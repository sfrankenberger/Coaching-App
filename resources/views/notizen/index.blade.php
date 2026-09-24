<x-layouts.app title="Meine Notizen">
    <p class="mb-2"><a href="{{ route('journal.index') }}" class="hinweis no-underline">&larr; Mein Journal</a></p>
    <h1 class="mb-3">Meine Notizen</h1>

    <x-karte :titel="$bearbeiten ? 'Notiz bearbeiten' : null">
        <form method="post" action="{{ $bearbeiten ? route('notizen.update', $bearbeiten) : route('notizen.store') }}" class="eingabe">
            @csrf
            <input name="title" class="feld" placeholder="Überschrift (optional)" maxlength="160" value="{{ old('title', $bearbeiten?->title) }}">
            <textarea name="body" class="feld" rows="4" placeholder="Was dir gerade durch den Kopf geht ..." required>{{ old('body', $bearbeiten?->body) }}</textarea>
            <div class="flex flex-wrap gap-2">
                @if ($kurse->count())
                    <label class="hinweis">Kurs<br><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="hinweis">Wer sieht das?<br><select name="visibility" class="feld">
                    @foreach (\App\Models\Note::VISIBILITIES as $k => $l)
                        @if ($k !== 'program' || $kurse->count())<option value="{{ $k }}" @selected(old('visibility', $bearbeiten?->visibility ?? 'private') === $k)>{{ $l }}</option>@endif
                    @endforeach
                </select></label>
                <label class="hinweis self-end flex items-center gap-2 pb-2"><input type="checkbox" name="is_pinned" value="1" class="size-5 accent-primary" @checked(old('is_pinned', $bearbeiten?->is_pinned))> Anpinnen</label>
            </div>
            <div class="eingabe-knoepfe">
                @if ($bearbeiten)<a href="{{ route('notizen.index') }}" class="knopf knopf-leise">Abbrechen</a>@endif
                <button type="submit" class="knopf">{{ $bearbeiten ? 'Speichern' : 'Notiz speichern' }}</button>
            </div>
        </form>
    </x-karte>

    <form method="get" class="my-3"><input type="search" name="q" value="{{ $suche }}" class="feld" placeholder="In deinen Notizen suchen"></form>

    @forelse ($notes as $n)
        <article id="notiz-{{ $n->id }}" @class(['karte', 'border-primary' => $n->is_pinned])>
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    @if ($n->title)<h2 class="text-lg">{{ $n->title }}</h2>@endif
                    <p class="text-base whitespace-pre-line">{{ $n->body }}</p>
                    <span class="hinweis block mt-2">
                        {{ $n->updated_at->translatedFormat('j. M Y, H:i') }}
                        @if ($n->notable) · zu «{{ $n->notable->title ?? '' }}» @endif
                        @if ($n->program) · {{ $n->program->title }} @endif
                        · {{ \App\Models\Note::VISIBILITIES[$n->visibility] ?? '' }}
                    </span>
                </div>
                <details class="relative shrink-0">
                    <summary class="list-none cursor-pointer size-8 grid place-items-center rounded-full hover:bg-page" aria-label="Mehr">···</summary>
                    <div class="absolute right-0 z-10 mt-1 w-44 rounded-xl border border-line bg-card p-1 shadow">
                        <a href="{{ route('notizen.index', ['bearbeiten' => $n->id]) }}" class="block rounded-lg px-3 py-2 text-md no-underline text-ink hover:bg-page">Bearbeiten</a>
                        <form method="post" action="{{ route('notizen.destroy', $n) }}" onsubmit="return confirm('Notiz löschen?')">@csrf @method('DELETE')<button class="block w-full rounded-lg px-3 py-2 text-left text-md text-danger hover:bg-page">Löschen</button></form>
                    </div>
                </details>
            </div>
        </article>
    @empty
        <x-karte><p class="text-ink-soft">Noch keine Notiz. Alles, was du dir merken willst, kommt hierher.</p></x-karte>
    @endforelse
</x-layouts.app>

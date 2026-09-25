<x-layouts.app title="Meine Notizen">
    <p style="margin:0 0 8px"><a href="{{ route('journal.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Mein Journal</a></p>
    <h1>Meine Notizen</h1>

    <div class="baustein">
        <p class="eyebrow" style="margin:0 0 10px">{{ $bearbeiten ? 'Notiz bearbeiten' : 'Neue Notiz' }}</p>
        <form method="post" action="{{ $bearbeiten ? route('notizen.update', $bearbeiten) : route('notizen.store') }}" class="eingabe">
            @csrf
            <input name="title" class="feld" placeholder="Überschrift (optional)" maxlength="160" value="{{ old('title', $bearbeiten?->title) }}">
            <textarea name="body" class="feld" rows="4" placeholder="Was dir gerade durch den Kopf geht ..." required>{{ old('body', $bearbeiten?->body) }}</textarea>
            <div class="flex flex-wrap gap-2">
                @if ($kurse->count())
                    <label class="block"><span class="feld-label">Kurs</span><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="block"><span class="feld-label">Wer sieht das?</span><select name="visibility" class="feld">
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
    </div>

    <form method="get" class="suche" style="margin:14px 0 16px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="{{ $suche }}" placeholder="In deinen Notizen suchen" aria-label="In deinen Notizen suchen"></form>

    @forelse ($notes as $n)
        <article id="notiz-{{ $n->id }}" @class(['karte', 'heute' => $n->is_pinned])>
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    @if ($n->title)<span class="t">@if ($n->is_pinned)<i class="fa-solid fa-thumbtack" style="color:var(--c-primary);font-size:12px;margin-right:6px"></i>@endif{{ $n->title }}</span>@endif
                    <p class="lesetext whitespace-pre-line" style="margin:4px 0 0">{{ $n->body }}</p>
                    <span class="m" style="margin-top:8px">
                        {{ $n->updated_at->translatedFormat('j. M Y, H:i') }}
                        @if ($n->notable) · zu «{{ $n->notable->title ?? '' }}» @endif
                        @if ($n->program) · {{ $n->program->title }} @endif
                        · {{ \App\Models\Note::VISIBILITIES[$n->visibility] ?? '' }}
                    </span>
                    <x-kommentare :item="$n" />
                </div>
                <details class="relative shrink-0">
                    <summary class="list-none cursor-pointer knopf-rund grid place-items-center" aria-label="Mehr"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
                    <div class="menue" style="right:0;top:36px">
                        <a href="{{ route('notizen.index', ['bearbeiten' => $n->id]) }}" class="e"><i class="fa-solid fa-pen"></i>Bearbeiten</a>
                        <form method="post" action="{{ route('notizen.destroy', $n) }}" onsubmit="return confirm('Notiz löschen?')">@csrf @method('DELETE')<button class="e gefahr"><i class="fa-solid fa-trash"></i>Löschen</button></form>
                    </div>
                </details>
            </div>
        </article>
    @empty
        <div class="leer"><i class="fa-regular fa-note-sticky"></i>Noch keine Notiz. Alles, was du dir merken willst, kommt hierher.</div>
    @endforelse
</x-layouts.app>

<x-layouts.app title="Meine Aufgaben">
    <p style="margin:0 0 8px"><a href="{{ route('journal.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Mein Journal</a></p>
    <h1>Meine Aufgaben</h1>

    <div class="baustein">
        <p class="eyebrow" style="margin:0 0 10px">{{ $bearbeiten ? 'Aufgabe bearbeiten' : 'Neue Aufgabe' }}</p>
        <form method="post" action="{{ $bearbeiten ? route('aufgaben.update', $bearbeiten) : route('aufgaben.store') }}" class="eingabe">
            @csrf
            <input name="title" class="feld" placeholder="Was nimmst du dir vor?" maxlength="160" required value="{{ old('title', $bearbeiten?->title) }}">
            <textarea name="body" class="feld" rows="2" placeholder="Notiz dazu (optional)">{{ old('body', $bearbeiten?->body) }}</textarea>
            <div class="flex flex-wrap gap-2">
                <label class="block"><span class="feld-label">Bis</span><input type="date" name="due_at" class="feld" value="{{ old('due_at', $bearbeiten?->due_at?->toDateString()) }}"></label>
                <label class="block"><span class="feld-label">Uhrzeit</span><input type="time" name="due_time" class="feld" value="{{ old('due_time', $bearbeiten?->due_time) }}"></label>
                @if ($kurse->count())
                    <label class="block"><span class="feld-label">Kurs</span><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="block"><span class="feld-label">Wer sieht das?</span><select name="visibility" class="feld">
                    <option value="private" @selected(old('visibility', $bearbeiten?->visibility) === 'private')>Nur ich</option>
                    <option value="coach" @selected(old('visibility', $bearbeiten?->visibility) === 'coach')>Meine Coachin</option>
                    @if ($kurse->count())<option value="program" @selected(old('visibility', $bearbeiten?->visibility) === 'program')>Mein Kurs</option>@endif
                </select></label>
            </div>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-md"><input type="checkbox" name="is_daily" value="1" class="size-5 accent-primary" @checked(old('is_daily', $bearbeiten?->is_daily))> Jeden Tag</label>
                <label class="flex items-center gap-2 text-md"><input type="checkbox" name="is_pinned" value="1" class="size-5 accent-primary" @checked(old('is_pinned', $bearbeiten?->is_pinned))> Anheften</label>
            </div>
            <div class="eingabe-knoepfe">
                @if ($bearbeiten)<a href="{{ route('aufgaben.index') }}" class="knopf knopf-leise">Abbrechen</a>@endif
                <button type="submit" class="knopf">{{ $bearbeiten ? 'Speichern' : 'Aufgabe hinzufügen' }}</button>
            </div>
        </form>
    </div>

    <form method="get" class="suche" style="margin:14px 0 16px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="{{ $suche }}" placeholder="In deinen Aufgaben suchen" aria-label="In deinen Aufgaben suchen"></form>

    @forelse ($offen as $t)
        @include('aufgaben._karte', ['t' => $t])
    @empty
        <x-leer icon="circle-check">Nichts offen. Schön. Wenn dir etwas einfällt, schreib es oben auf, dann bleibt es nicht im Kopf.</x-leer>
    @endforelse

    @if ($fertig->isNotEmpty())
        <details class="mt-4">
            <summary class="knopf knopf-anstoss" style="margin:0 0 10px"><i class="fa-solid fa-check"></i>{{ $fertig->count() }} erledigt</summary>
            @foreach ($fertig as $t)
                @include('aufgaben._karte', ['t' => $t])
            @endforeach
        </details>
    @endif
</x-layouts.app>

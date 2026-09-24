<x-layouts.app title="Meine Aufgaben">
    <p class="mb-2"><a href="{{ route('journal.index') }}" class="hinweis no-underline">&larr; Mein Journal</a></p>
    <h1 class="mb-3">Meine Aufgaben</h1>

    <x-karte :titel="$bearbeiten ? 'Aufgabe bearbeiten' : null">
        <form method="post" action="{{ $bearbeiten ? route('aufgaben.update', $bearbeiten) : route('aufgaben.store') }}" class="eingabe">
            @csrf
            <input name="title" class="feld" placeholder="Was nimmst du dir vor?" maxlength="160" required value="{{ old('title', $bearbeiten?->title) }}">
            <textarea name="body" class="feld" rows="2" placeholder="Notiz dazu (optional)">{{ old('body', $bearbeiten?->body) }}</textarea>
            <div class="flex flex-wrap gap-2">
                <label class="hinweis">Bis<br><input type="date" name="due_at" class="feld" value="{{ old('due_at', $bearbeiten?->due_at?->toDateString()) }}"></label>
                <label class="hinweis">Uhrzeit<br><input type="time" name="due_time" class="feld" value="{{ old('due_time', $bearbeiten?->due_time) }}"></label>
                @if ($kurse->count())
                    <label class="hinweis">Kurs<br><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="hinweis">Wer sieht das?<br><select name="visibility" class="feld">
                    <option value="private" @selected(old('visibility', $bearbeiten?->visibility) === 'private')>Nur ich</option>
                    <option value="coach" @selected(old('visibility', $bearbeiten?->visibility) === 'coach')>Meine Coachin</option>
                    @if ($kurse->count())<option value="program" @selected(old('visibility', $bearbeiten?->visibility) === 'program')>Mein Kurs</option>@endif
                </select></label>
            </div>
            <label class="flex items-center gap-2 text-md"><input type="checkbox" name="is_daily" value="1" class="size-5 accent-primary" @checked(old('is_daily', $bearbeiten?->is_daily))> Jeden Tag</label>
            <div class="eingabe-knoepfe">
                @if ($bearbeiten)<a href="{{ route('aufgaben.index') }}" class="knopf knopf-leise">Abbrechen</a>@endif
                <button type="submit" class="knopf">{{ $bearbeiten ? 'Speichern' : 'Aufgabe hinzufügen' }}</button>
            </div>
        </form>
    </x-karte>

    <form method="get" class="my-3"><input type="search" name="q" value="{{ $suche }}" class="feld" placeholder="In deinen Aufgaben suchen"></form>

    @forelse ($offen as $t)
        @include('aufgaben._karte', ['t' => $t])
    @empty
        <x-karte><p class="text-ink-soft">Nichts offen. Schreib auf, was als Nächstes dran ist.</p></x-karte>
    @endforelse

    @if ($fertig->isNotEmpty())
        <details class="mt-4">
            <summary class="hinweis cursor-pointer mb-2">{{ $fertig->count() }} erledigt</summary>
            @foreach ($fertig as $t)
                @include('aufgaben._karte', ['t' => $t])
            @endforeach
        </details>
    @endif
</x-layouts.app>

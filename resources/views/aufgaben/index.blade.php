<x-layouts.app title="Meine Aufgaben">
    @if (App\Support\Funktionen::an('zeitleiste'))<p class="m-0 mb-2"><a href="{{ route('journal.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Mein Journal</a></p>@endif
    <h1>Meine Aufgaben</h1>

    <details class="aufklapp-formular" @if ($bearbeiten || $errors->any() || request()->query('neu')) open @endif>
        <summary class="knopf m-0"><i class="fa-solid fa-plus"></i>{{ $bearbeiten ? 'Aufgabe bearbeiten' : 'Neue Aufgabe' }}</summary>
    <div class="baustein mt-2.5">
        <form method="post" action="{{ $bearbeiten ? route('aufgaben.update', $bearbeiten) : route('aufgaben.store') }}" class="eingabe">
            @csrf
            <input name="title" class="feld" placeholder="Was nimmst du dir vor?" maxlength="160" required value="{{ old('title', $bearbeiten?->title) }}">
            <textarea name="body" class="feld" rows="2" placeholder="Notiz dazu (optional)">{{ old('body', $bearbeiten?->body) }}</textarea>
            <x-anhang-wahl :refs="old('refs', $bearbeiten?->anhangRefs() ?? [])" />
            <x-projekt-wahl :projekte="$projekte" :value="old('project_id', $bearbeiten?->project_id)" />
            <div class="flex flex-wrap gap-2">
                <label class="block"><span class="feld-label">Bis</span><input type="date" name="due_at" class="feld" value="{{ old('due_at', $bearbeiten?->due_at?->toDateString()) }}"></label>
                <label class="block"><span class="feld-label">Uhrzeit</span><input type="time" name="due_time" class="feld" value="{{ old('due_time', $bearbeiten?->due_time) }}"></label>
                @if ($kurse->count())
                    <label class="block"><span class="feld-label">Gehört zu</span><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="block"><span class="feld-label">Wer sieht das?</span><select name="visibility" class="feld">
                    <option value="private" @selected(old('visibility', $bearbeiten?->visibility) === 'private')>Nur ich</option>
                    <option value="coach" @selected(old('visibility', $bearbeiten?->visibility) === 'coach')>{{ app(\App\Tenancy\Branding::class)->coachName() }}</option>
                    @if ($gemeinschaft->count())<option value="program" @selected(old('visibility', $bearbeiten?->visibility) === 'program')>Mein Kurs</option>
                    <option value="all" @selected(old('visibility', $bearbeiten?->visibility) === 'all')>In der Community</option>@endif
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
    </details>

    <x-filterleiste :filter="$filter" :projekte="$projekte" :kurse="$gemeinschaft->all()" platzhalter="In deinen Aufgaben suchen" />

    @forelse ($offen as $t)
        @include('aufgaben._karte', ['t' => $t])
    @empty
        <x-leer icon="circle-check">Nichts offen. Schön. Wenn dir etwas einfällt, schreib es oben auf, dann bleibt es nicht im Kopf.</x-leer>
    @endforelse

    @if ($fertig->isNotEmpty())
        <details class="mt-4">
            <summary class="knopf knopf-anstoss m-0 mb-2.5"><i class="fa-solid fa-check"></i>{{ $fertig->count() }} erledigt</summary>
            @foreach ($fertig as $t)
                @include('aufgaben._karte', ['t' => $t])
            @endforeach
        </details>
    @endif
</x-layouts.app>

{{-- Frage stellen: im Kursraum (program fest) oder in der Community (Kurs waehlbar). --}}
@props(['action', 'coach', 'vorgabe', 'aufgabe' => null, 'program' => null, 'programme' => null, 'erwaehnbar' => null, 'offen' => false])
<details id="neu" class="baustein"{{ $errors->any() || $aufgabe || $offen ? ' open' : '' }}>
    <summary class="knopf knopf-anstoss cursor-pointer"><i class="fa-solid fa-circle-question"></i>Frage stellen</summary>
    <form method="post" action="{{ $action }}" class="eingabe mt-3.5" data-entwurf="frage-{{ $program?->id ?? 'community' }}">
        @csrf
        @if ($aufgabe)
            <input type="hidden" name="aufgabe_id" value="{{ $aufgabe->id }}">
            <p class="hinweis m-0"><i class="fa-solid fa-list-check"></i> Zur Aufgabe «{{ $aufgabe->title }}». Abschicken hakt sie ab.</p>
        @endif
        @if ($programme)
            <div>
                <label for="frage-kurs" class="feld-label">In welchem Kurs?</label>
                <select id="frage-kurs" name="program_id" class="feld" required>
                    @foreach ($programme as $p)<option value="{{ $p->id }}" @selected((int) old('program_id', $programme->first()?->id) === $p->id)>{{ $p->title }}</option>@endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="frage-titel" class="feld-label">Deine Frage in einem Satz</label>
            <input id="frage-titel" name="title" class="feld" maxlength="200" required value="{{ old('title', $vorgabe['title'] ?? '') }}">
            @error('title')<p class="fehler mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="frage-text" class="feld-label">Mehr dazu, freiwillig</label>
            <textarea id="frage-text" name="body" class="feld" rows="4" placeholder="Was ist passiert, was hast du schon versucht? Mit @Name sprichst du jemanden direkt an." @if ($erwaehnbar) data-erwaehnen='@json($erwaehnbar)' @endif>{{ old('body', $vorgabe['body'] ?? '') }}</textarea>
        </div>
        <x-anhang-wahl :refs="old('refs', $vorgabe['refs'] ?? [] ?: ($aufgabe ? ['task:'.$aufgabe->id] : []))" />
        <div>
            <span class="feld-label">Wer sieht die Frage?</span>
            <label class="flex items-center gap-2 text-md" style="margin:4px 0"><input type="radio" name="visibility" value="program" checked class="accent-primary"> {{ $program && ! $program->gemeinschaft() ? 'In der Community' : 'Alle im Kurs' }}</label>
            <label class="flex items-center gap-2 text-md"><input type="radio" name="visibility" value="coach" class="accent-primary"> Nur {{ $coach }}</label>
        </div>
        <div class="eingabe-knoepfe"><button type="submit" class="knopf"><i class="fa-solid fa-paper-plane"></i>Frage stellen</button></div>
    </form>
</details>

{{-- Fragenliste mit Filter, Sortierung und Suche; fuer den Kursraum und die Community. --}}
@props(['fragen', 'filter', 'sort', 'q', 'coach', 'basis', 'mitKurs' => false])
@php $ich = auth()->id(); @endphp
<form method="get" action="{{ $basis['url'] }}" class="filterleiste-fragen">
    @foreach ($basis['fest'] ?? [] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <input type="hidden" name="f" value="{{ $filter }}">
    <div class="pillen">
        @foreach (\App\Http\Controllers\FragenController::FILTER as $k => $l)
            <button type="submit" name="f" value="{{ $k }}" @class(['pille', 'an' => $filter === $k])>{{ $l }}</button>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" class="feld flex-1 min-w-40" placeholder="Fragen durchsuchen" aria-label="Suchen">
        <select name="sort" class="pille" onchange="this.form.requestSubmit()" aria-label="Sortierung">
            @foreach (\App\Http\Controllers\FragenController::SORT as $k => $l)<option value="{{ $k }}" @selected($sort === $k)>{{ $l }}</option>@endforeach
        </select>
        <button type="submit" class="knopf knopf-leise knopf-klein" aria-label="Suchen"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
</form>

@forelse ($fragen as $f)
    @php $stand = $f->states->first(); $neu = $f->last_answer_at && (! $stand?->seen_at || $stand->seen_at->lt($f->last_answer_at)); @endphp
    <a href="{{ route('fragen.show', $f) }}" @class(['karte frage-zeile', 'block no-underline', 'neu' => $f->status === 'offen'])>
        <span class="flex flex-wrap items-center gap-2 mb-1.5">
            @if ($mitKurs && $f->program)<span class="chip chip-kurs" style="--kc: {{ $f->program->color ?: '#7C8C9A' }}">{{ $f->program->title }}</span>@endif
            <span @class(['chip', 'chip-coach' => $f->status === 'call', 'chip-gut' => in_array($f->status, ['beantwortet', 'besprochen'], true)])>{{ $f->statusLabel() }}</span>
            @if ($f->visibility === 'coach')<span class="chip"><i class="fa-solid fa-lock"></i>Nur {{ $coach }}</span>@endif
            @if ($neu)<span class="chip chip-neu">Neue Antworten</span>@endif
        </span>
        <span class="flex items-start gap-3">
            <x-avatar :user="$f->user" :size="36" class="shrink-0" />
            <span class="min-w-0 flex-1">
                <span class="t block">{{ $f->title }}</span>
                <span class="m">
                    {{ $f->user?->vorname() }} · {{ $f->created_at->translatedFormat('j. F') }}
                    · {{ $f->answers_count }} {{ $f->answers_count === 1 ? 'Antwort' : 'Antworten' }}
                    @if ($f->call_wuensche) · <i class="fa-solid fa-bullseye"></i> {{ $f->call_wuensche }}× für den Call gewünscht @endif
                </span>
            </span>
        </span>
    </a>
@empty
    <x-leer icon="circle-question">{{ $filter || $q ? 'Keine Fragen in dieser Auswahl.' : 'Noch keine Fragen. Mach gern den Anfang, es gibt keine dummen.' }}</x-leer>
@endforelse

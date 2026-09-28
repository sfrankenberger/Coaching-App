<x-layouts.app title="Community">
    <h1 class="mb-1">Community</h1>
    <p class="unterzeile m-0 mb-3">Hier laufen alle Fragen und alles zusammen, was jemand aus den Kursen teilt. Du kannst kommentieren, reagieren und Erfahrungen weitergeben. Was nur {{ $coach }} lesen soll, schreibst du im 1:1 Coaching.</p>

    @if ($programme->isEmpty())
        <x-leer icon="comments">Sobald du in einem Kurs bist, findest du hier die Fragen aus der Gruppe.</x-leer>
    @else
        <div class="flex flex-wrap gap-2 mb-3">
            <a href="{{ route('community.leute', array_filter(['k' => $kurs?->id])) }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-users"></i>Wer ist dabei</a>
            <a href="{{ route('kurse.fragen', $kurs ?? $programme->first()) }}" class="knopf knopf-klein"><i class="fa-solid fa-plus"></i>Was beschäftigt dich?</a>
        </div>

        @if ($programme->count() > 1)
            <div class="pillen">
                <a href="{{ route('community', array_filter(['f' => $filter])) }}" @class(['pille', 'an' => ! $kurs])>Alle</a>
                @foreach ($programme as $p)
                    <a href="{{ route('community', array_filter(['k' => $p->id, 'f' => $filter])) }}" @class(['pille', 'an' => $kurs?->id === $p->id])>{{ $p->title }}</a>
                @endforeach
            </div>
        @endif
        <div class="pillen">
            @foreach (['' => 'Alle', 'offen' => 'Offen', 'call' => 'Für den Call', 'erledigt' => 'Beantwortet'] as $k => $l)
                <a href="{{ route('community', array_filter(['k' => $kurs?->id, 'f' => $k])) }}" @class(['pille', 'an' => $filter === $k])>{{ $l }}</a>
            @endforeach
        </div>

        @forelse ($fragen as $f)
            <a href="{{ route('fragen.show', $f) }}" @class(['karte', 'block no-underline', 'neu' => $f->status === 'offen'])>
                <span class="flex flex-wrap items-center gap-2 mb-1.5">
                    @if (! $kurs && $programme->count() > 1 && $f->program)<span class="chip chip-kurs" style="--kc: {{ $f->program->color ?: '#7C8C9A' }}">{{ $f->program->title }}</span>@endif
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
            <p class="hinweis">Noch keine Fragen. Stell die erste, sie hilft oft auch den anderen.</p>
        @endforelse
    @endif
</x-layouts.app>

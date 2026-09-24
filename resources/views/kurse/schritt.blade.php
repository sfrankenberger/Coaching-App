<x-layouts.app :title="$schritt->title">
    <p class="mb-2"><a href="{{ route('kurse.show', $program) }}" class="hinweis no-underline">&larr; {{ $program->title }}</a></p>

    <div class="flex items-center gap-2 mb-3">
        @if ($vorher)
            <a href="{{ route('kurse.schritt', [$program, $vorher]) }}" class="knopf knopf-leise" style="min-height:36px;padding:6px 12px" aria-label="Schritt davor">&larr;</a>
        @endif
        <span class="hinweis uppercase tracking-wider text-xs font-semibold flex-1 text-center">
            {{ $program->pacing === 'weekly' ? 'Woche' : 'Schritt' }} {{ $schritt->week_number ?? $nummer }} von {{ $anzahl }}
        </span>
        @if ($nachher && ($nachher->isUnlocked($program) || auth()->user()->canManageCurrentTenant()))
            <a href="{{ route('kurse.schritt', [$program, $nachher]) }}" class="knopf knopf-leise" style="min-height:36px;padding:6px 12px" aria-label="Schritt danach">&rarr;</a>
        @endif
    </div>

    <x-karte>
        <h1>{{ $schritt->title }}</h1>
        @if ($schritt->unlocks_at && $program->pacing === 'weekly')
            <p class="hinweis mt-1">ab {{ $schritt->unlocks_at->translatedFormat('l, j. F') }}</p>
        @endif
        @if ($schritt->summary)
            <div class="prose-app mt-3">{!! $schritt->summary !!}</div>
        @endif
    </x-karte>

    @forelse ($units as $unit)
        @include('kurse._einheit-zeile', ['unit' => $unit, 'erledigt' => $done->contains($unit->id)])
    @empty
        <x-karte><p class="text-ink-soft">Hier kommt noch etwas. Schau bald wieder rein.</p></x-karte>
    @endforelse
</x-layouts.app>

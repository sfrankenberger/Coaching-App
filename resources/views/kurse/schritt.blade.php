<x-layouts.app :title="$schritt->title">
    <div style="--kc: {{ $program->color ?: '#7C8C9A' }}">
        <div class="flex items-center gap-3" style="margin:0 0 6px">
            <a href="{{ route('kurse.show', $program) }}" class="knopf knopf-ruhig" style="width:44px;padding:0;flex:none" aria-label="Zurück zum Kurs"><i class="fa-solid fa-chevron-left"></i></a>
            <span class="min-w-0 flex-1">
                <span class="eyebrow block">Kurs</span>
                <span class="hinweis block truncate">{{ $program->title }}</span>
            </span>
            @if ($vorher)
                <a href="{{ route('kurse.schritt', [$program, $vorher]) }}" class="knopf knopf-rund" aria-label="Schritt davor"><i class="fa-solid fa-chevron-left"></i></a>
            @endif
            @if ($nachher && ($nachher->isUnlocked($program) || auth()->user()->canManageCurrentTenant()))
                <a href="{{ route('kurse.schritt', [$program, $nachher]) }}" class="knopf knopf-rund" aria-label="Schritt danach"><i class="fa-solid fa-chevron-right"></i></a>
            @endif
        </div>

        <p class="eyebrow" style="color:var(--c-primary);margin:18px 0 4px">{{ $program->pacing === 'weekly' ? 'Woche' : 'Schritt' }} {{ $schritt->week_number ?? $nummer }} von {{ $anzahl }}</p>
        <h1 style="margin:0 0 4px">{{ $schritt->title }}</h1>
        @if ($schritt->unlocks_at && $program->pacing === 'weekly')
            <p class="unterzeile" style="margin:0 0 12px">ab {{ $schritt->unlocks_at->translatedFormat('l, j. F') }}</p>
        @endif
        @if ($schritt->summary)
            <div class="karte"><div class="prose-app">{!! $schritt->summary !!}</div></div>
        @endif

        <h2 class="abschnitt"><i class="fa-solid fa-circle-play"></i>{{ $program->pacing === 'weekly' ? 'Diese Woche' : 'Lektionen' }}<em>{{ $units->count() }}</em></h2>
        @if ($units->isEmpty())
            <div class="leer"><i class="fa-regular fa-hourglass"></i>Hier kommt noch etwas. Schau bald wieder rein.</div>
        @else
            <div class="modul einzeln">
                @foreach ($units as $unit)
                    @include('kurse._einheit-zeile', ['unit' => $unit, 'erledigt' => $done->contains($unit->id)])
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>

<x-layouts.app :title="$schritt->title">
    @php $ich = auth()->user(); @endphp
    <div style="--kc: {{ $program->color ?: '#7C8C9A' }}">
        <div class="flex items-center gap-3 m-0 mb-1.5">
            <a href="{{ route('kurse.show', $program) }}" class="knopf knopf-ruhig knopf-quadrat" aria-label="Zurück zum Kurs"><i class="fa-solid fa-chevron-left"></i></a>
            <span class="min-w-0 flex-1">
                <span class="eyebrow block">Kurs</span>
                <span class="hinweis block truncate">{{ $program->title }}</span>
            </span>
            @if ($vorher)
                <a href="{{ route('kurse.schritt', [$program, $vorher]) }}" class="knopf knopf-rund" aria-label="Schritt davor"><i class="fa-solid fa-chevron-left"></i></a>
            @endif
            @if ($nachher && ($nachher->isUnlocked($program) || $ich->canManageCurrentTenant()))
                <a href="{{ route('kurse.schritt', [$program, $nachher]) }}" class="knopf knopf-rund" aria-label="Schritt danach"><i class="fa-solid fa-chevron-right"></i></a>
            @endif
        </div>

        <p class="eyebrow" style="color:var(--c-primary);margin:18px 0 4px">{{ $program->pacing === 'weekly' ? 'Woche' : 'Schritt' }} {{ $schritt->week_number ?? $nummer }} von {{ $anzahl }}</p>
        <h1 class="m-0 mb-1">{{ $schritt->title }}</h1>
        @if ($schritt->unlocks_at && $program->pacing === 'weekly')
            <p class="unterzeile m-0 mb-3">ab {{ $schritt->unlocks_at->translatedFormat('l, j. F') }}</p>
        @endif
        @if ($schritt->summary)
            <div class="karte"><div class="prose-app">{!! $schritt->summary !!}</div></div>
        @endif

        {{-- Calls dieser Woche: vorher Zoom, danach Aufzeichnung --}}
        @foreach ($termine->reject(fn ($t) => in_array($t->type, \App\Models\Event::ALL_DAY_TYPES, true)) as $t)
            <x-termin-karte class="mt-3" :termin="$t" />
        @endforeach

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

        {{-- Aufgaben der Woche: von der Coachin und eigene --}}
        <h2 class="abschnitt"><i class="fa-solid fa-list-check"></i>Deine Aufgaben diese Woche @if ($aufgaben->whereNull('done_at')->count())<em>{{ $aufgaben->whereNull('done_at')->count() }}</em>@endif</h2>
        @foreach ($aufgaben as $t)
            @include('aufgaben._karte', ['t' => $t])
        @endforeach
        @unless ($ich->canManageCurrentTenant())
            <form method="post" action="{{ route('aufgaben.store') }}" class="baustein mt-1">
                @csrf
                <input type="hidden" name="program_id" value="{{ $program->id }}">
                <input type="hidden" name="step_id" value="{{ $schritt->id }}">
                <input type="hidden" name="visibility" value="coach">
                <input type="hidden" name="zurueck" value="{{ url()->current() }}">
                <label for="vorhaben" class="eyebrow block m-0 mb-2">Was nimmst du dir diese Woche vor?</label>
                <div class="flex gap-2">
                    <input id="vorhaben" name="title" class="feld" maxlength="160" required placeholder="Ein kleiner, konkreter Schritt">
                    <button type="submit" class="knopf" aria-label="Aufgabe anlegen" style="flex:none"><i class="fa-solid fa-plus"></i></button>
                </div>
                <p class="hinweis m-0 mt-2">Deine Coachin sieht, was du dir vornimmst. Du kannst es im Journal ändern.</p>
            </form>
        @endunless

        {{-- Material der Woche und ihrer Lektionen --}}
        @if ($material->isNotEmpty())
            <h2 class="abschnitt"><i class="fa-solid fa-folder-open"></i>Material<em>{{ $material->count() }}</em></h2>
            @foreach ($material as $r)
                @include('kurse._material', ['r' => $r])
            @endforeach
        @endif

        {{-- Reflexions- und Fragentag --}}
        @foreach ($termine->filter(fn ($t) => in_array($t->type, \App\Models\Event::ALL_DAY_TYPES, true)) as $t)
            <a href="{{ $t->type === 'reflection_day' ? route('reflexion.index') : route('gespraech.index') }}" class="zeile" style="margin-top:12px">
                <span class="ic"><i class="fa-solid fa-{{ $t->type === 'reflection_day' ? 'pen-to-square' : 'circle-question' }}"></i></span>
                <span class="tx"><b>{{ $t->type === 'reflection_day' ? 'Reflexion schreiben' : 'Frage stellen' }}</b><span>{{ $t->typeLabel() }} · {{ $t->starts_at->translatedFormat('l, j. F') }}</span></span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    </div>
</x-layouts.app>

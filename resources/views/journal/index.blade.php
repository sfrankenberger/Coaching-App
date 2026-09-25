<x-layouts.app title="Mein Journal">
    <h1>Mein Journal</h1>
    <p class="unterzeile m-0 mb-3.5">Dein Platz zum Schreiben. Alles bleibt bei dir, bis du es selbst teilst.</p>

    <a href="{{ route('aufgaben.index') }}" class="zeile">
        <span class="ic"><i class="fa-solid fa-list-check"></i></span>
        <span class="tx"><b>Meine Aufgaben</b><span>{{ $offeneAufgaben }} offen</span></span>
        <i class="fa-solid fa-chevron-right pf"></i>
    </a>
    <a href="{{ route('notizen.index') }}" class="zeile">
        <span class="ic"><i class="fa-solid fa-note-sticky"></i></span>
        <span class="tx"><b>Meine Notizen</b><span>{{ $notizen }} {{ $notizen === 1 ? 'Notiz' : 'Notizen' }}</span></span>
        <i class="fa-solid fa-chevron-right pf"></i>
    </a>
    <a href="{{ route('reflexion.index') }}" class="zeile">
        <span class="ic"><i class="fa-solid fa-pen-to-square"></i></span>
        <span class="tx"><b>Wochenreflexion</b><span>{{ $letzteReflexion ? 'Zuletzt '.$letzteReflexion->created_at->translatedFormat('j. F') : 'Noch keine' }}</span></span>
        <i class="fa-solid fa-chevron-right pf"></i>
    </a>

    @if ($eintraege->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-book-open"></i>Frühere Einträge<em>{{ $eintraege->count() }}</em></h2>
        @foreach ($eintraege as $e)
            <article class="karte">
                @if ($e->title)<span class="t">{{ $e->title }}</span>@endif
                @if ($e->body)<p class="x whitespace-pre-line m-0 mt-1">{{ $e->body }}</p>@endif
                <span class="m mt-1.5">{{ $e->created_at->translatedFormat('j. M Y') }}@if ($e->url) · <a href="{{ $e->url }}" target="_blank" rel="noopener">Link</a>@endif</span>
            </article>
        @endforeach
    @endif
</x-layouts.app>

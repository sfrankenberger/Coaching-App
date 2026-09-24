<x-layouts.app title="Mein Journal">
    <h1 class="mb-3">Mein Journal</h1>

    <a href="{{ route('aufgaben.index') }}" class="karte flex items-center gap-3 no-underline text-ink hover:border-primary">
        <span class="size-10 shrink-0 rounded-xl bg-page grid place-items-center text-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M9 6h11M9 12h11M9 18h11M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/></svg></span>
        <span class="flex-1"><span class="block text-base">Meine Aufgaben</span><span class="hinweis">{{ $offeneAufgaben }} offen</span></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><path d="m9 6 6 6-6 6"/></svg>
    </a>
    <a href="{{ route('notizen.index') }}" class="karte flex items-center gap-3 no-underline text-ink hover:border-primary">
        <span class="size-10 shrink-0 rounded-xl bg-page grid place-items-center text-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M4 20h16M6 16l10-10 2 2L8 18l-3 1z"/></svg></span>
        <span class="flex-1"><span class="block text-base">Meine Notizen</span><span class="hinweis">{{ $notizen }} {{ $notizen === 1 ? 'Notiz' : 'Notizen' }}</span></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><path d="m9 6 6 6-6 6"/></svg>
    </a>
    <a href="{{ route('reflexion.index') }}" class="karte flex items-center gap-3 no-underline text-ink hover:border-primary">
        <span class="size-10 shrink-0 rounded-xl bg-page grid place-items-center text-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <span class="flex-1"><span class="block text-base">Wochenreflexion</span><span class="hinweis">{{ $letzteReflexion ? 'Zuletzt '.$letzteReflexion->created_at->translatedFormat('j. F') : 'Noch keine' }}</span></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><path d="m9 6 6 6-6 6"/></svg>
    </a>

    @if ($eintraege->isNotEmpty())
        <h2 class="mt-5 mb-2">Frühere Einträge</h2>
        @foreach ($eintraege as $e)
            <article class="karte">
                @if ($e->title)<h3 class="text-base">{{ $e->title }}</h3>@endif
                @if ($e->body)<p class="text-md whitespace-pre-line">{{ $e->body }}</p>@endif
                <span class="hinweis block mt-1">{{ $e->created_at->translatedFormat('j. M Y') }}@if ($e->url) · <a href="{{ $e->url }}" target="_blank" rel="noopener">Link</a>@endif</span>
            </article>
        @endforeach
    @endif
</x-layouts.app>

<x-layouts.app :title="$conv->isDirect() ? '1:1 mit '.$gegenueber : $gegenueber">
    @php $ich = auth()->user(); @endphp
    <div class="flex items-center gap-3 mb-3">
        @if ($ich->canManageCurrentTenant())
            <a href="{{ route('gespraech.index') }}" class="hinweis no-underline">&larr; Gespräche</a>
        @elseif (! $conv->isDirect() && $conv->program)
            <a href="{{ route('kurse.show', $conv->program) }}" class="hinweis no-underline">&larr; {{ $conv->program->title }}</a>
        @endif
        <h1 class="text-xl flex-1">{{ $conv->isDirect() ? 'Gespräch mit '.$gegenueber : 'Austausch: '.$gegenueber }}</h1>
    </div>

    <div id="verlauf" class="flex flex-col gap-2" data-verlauf="{{ route('gespraech.neu', $conv) }}" data-letzte="{{ $messages->last()?->id ?? 0 }}" data-ich="{{ $ich->id }}">
        @if ($versteckt)
            <a href="{{ route('gespraech.show', [$conv, 'alle' => 1]) }}" class="knopf knopf-leise self-center" style="min-height:36px;padding:6px 14px">{{ $versteckt }} ältere Nachrichten zeigen</a>
        @endif
        @forelse ($messages as $m)
            @include('gespraech._nachricht', ['m' => $m])
        @empty
            <p class="hinweis text-center py-6" data-leer>{{ $conv->isDirect() ? 'Noch keine Nachricht. Schreib, was dich beschäftigt.' : 'Noch nichts geschrieben. Mach den Anfang.' }}</p>
        @endforelse
    </div>

    <form method="post" action="{{ route('gespraech.senden', $conv) }}" enctype="multipart/form-data" class="karte mt-3 sticky bottom-[72px] sm:bottom-4" data-senden>
        @csrf
        <div class="flex items-end gap-2">
            <label class="size-10 shrink-0 grid place-items-center rounded-full border border-line cursor-pointer text-muted hover:text-primary" title="Foto oder Datei anhängen">
                <input type="file" name="file" class="hidden" accept="image/*,application/pdf,audio/*">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M12 5v14M5 12h14"/></svg>
            </label>
            <textarea name="body" rows="1" class="feld flex-1" placeholder="Nachricht an {{ $gegenueber }}" style="min-height:44px;max-height:160px"></textarea>
            <button type="button" class="size-10 shrink-0 grid place-items-center rounded-full border border-line text-muted hover:text-primary" data-sprache title="Sprachnachricht aufnehmen">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
            </button>
            <button type="submit" class="size-10 shrink-0 grid place-items-center rounded-full bg-primary text-primary-contrast" title="Senden" aria-label="Senden">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5"><path d="M4 12 20 4l-4 16-4-7z"/></svg>
            </button>
        </div>
        <div class="hinweis mt-1 flex items-center gap-2" data-datei-name hidden></div>
        <div class="mt-2 flex items-center gap-3" data-aufnahme hidden>
            <span class="size-3 rounded-full bg-danger animate-pulse"></span>
            <span class="text-md">Aufnahme läuft <span data-aufnahme-zeit>0:00</span></span>
            <button type="button" class="knopf knopf-leise ml-auto" style="min-height:36px;padding:6px 12px" data-aufnahme-stopp>Fertig</button>
            <button type="button" class="knopf knopf-leise" style="min-height:36px;padding:6px 12px" data-aufnahme-abbruch>Verwerfen</button>
        </div>
    </form>

    @push('scripts')
        <script>window.CHAT = { emojis: @json(collect(\App\Models\Reaction::EMOJIS)->map(fn ($e) => $e[0])) };</script>
    @endpush
</x-layouts.app>

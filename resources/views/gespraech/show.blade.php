<x-layouts.app :title="$conv->isDirect() ? '1:1 mit '.$gegenueber : $gegenueber" body="chat-seite">
    @php $ich = auth()->user(); @endphp
    <div class="flex items-center gap-3 m-0 mb-3">
        @if ($ich->canManageCurrentTenant())
            <a href="{{ route('gespraech.index') }}" class="knopf knopf-ruhig knopf-quadrat" aria-label="Alle Gespräche"><i class="fa-solid fa-chevron-left"></i></a>
        @elseif (! $conv->isDirect() && $conv->program)
            <a href="{{ route('kurse.show', $conv->program) }}" class="knopf knopf-ruhig" style="width:44px;padding:0;flex:none" aria-label="Zum Kurs"><i class="fa-solid fa-chevron-left"></i></a>
        @endif
        <span class="min-w-0">
            <span class="eyebrow block">{{ $conv->isDirect() ? 'Persönlich' : 'Austausch in der Gruppe' }}</span>
            <span class="block truncate" style="font-family:var(--font-heading);font-size:20px;line-height:1.3">{{ $conv->isDirect() ? 'Gespräch mit '.$gegenueber : $gegenueber }}</span>
        </span>
    </div>

    <div id="verlauf" class="flex flex-col gap-2" data-verlauf="{{ route('gespraech.neu', $conv) }}" data-letzte="{{ $messages->last()?->id ?? 0 }}" data-ich="{{ $ich->id }}">
        @if ($versteckt)
            <a href="{{ route('gespraech.show', [$conv, 'alle' => 1]) }}" class="knopf knopf-leise self-center knopf-klein">{{ $versteckt }} ältere Nachrichten zeigen</a>
        @endif
        @forelse ($messages as $m)
            @include('gespraech._nachricht', ['m' => $m])
        @empty
            <p class="hinweis text-center py-6" data-leer>{{ $conv->isDirect() ? 'Noch keine Nachricht. Schreib, was dich beschäftigt.' : 'Noch nichts geschrieben. Mach den Anfang.' }}</p>
        @endforelse
    </div>

    <form method="post" action="{{ route('gespraech.senden', $conv) }}" enctype="multipart/form-data" class="chat-eingabe" data-senden>
        @csrf
        <div class="chat-eingabe-in">
            <textarea name="body" rows="1" class="feld" placeholder="Nachricht an {{ $gegenueber }}" aria-label="Nachricht" style="min-height:48px;max-height:160px">{{ \Illuminate\Support\Str::limit((string) request()->query('entwurf'), 500, '') }}</textarea>
            <div class="hinweis flex items-center gap-2" data-datei-name hidden></div>
            <div class="flex items-center gap-3" data-aufnahme hidden>
                <span class="size-3 rounded-full bg-danger animate-pulse"></span>
                <span class="text-md">Aufnahme läuft <span data-aufnahme-zeit>0:00</span></span>
                <button type="button" class="knopf knopf-ruhig ml-auto knopf-klein" data-aufnahme-stopp>Fertig</button>
                <button type="button" class="knopf knopf-text knopf-klein" data-aufnahme-abbruch>Verwerfen</button>
            </div>
            <div class="chat-knoepfe">
                <label class="chat-rund" title="Foto oder Datei anhängen" aria-label="Datei anhängen">
                    <input type="file" name="file" class="hidden" accept="image/*,application/pdf,audio/*">
                    <i class="fa-solid fa-plus"></i>
                </label>
                <button type="button" class="chat-rund" data-sprache title="Sprachnachricht aufnehmen" aria-label="Sprachnachricht aufnehmen"><i class="fa-solid fa-microphone"></i></button>
                <span class="flex-1"></span>
                <button type="submit" class="chat-senden" title="Senden" aria-label="Senden"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>window.CHAT = { emojis: @json(collect(\App\Models\Reaction::EMOJIS)->map(fn ($e) => $e[0])) };</script>
    @endpush
</x-layouts.app>

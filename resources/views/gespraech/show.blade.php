<x-layouts.app :title="$conv->isDirect() ? '1:1 mit '.$gegenueber : $gegenueber" body="chat-seite">
    @php $ich = auth()->user(); @endphp
    <div class="chat-kopf">
    <div class="flex items-center gap-3 m-0 mb-3">
        @if (App\Coach\Ansicht::teamSicht($ich))
            <a href="{{ route('gespraech.index') }}" class="knopf knopf-ruhig knopf-quadrat" aria-label="Alle Gespräche"><i class="fa-solid fa-chevron-left"></i></a>
        @elseif (! $conv->isDirect() && $conv->program)
            <a href="{{ route('kurse.show', $conv->program) }}" class="knopf knopf-ruhig" style="width:44px;padding:0;flex:none" aria-label="Zum Kurs"><i class="fa-solid fa-chevron-left"></i></a>
        @endif
        <span class="min-w-0">
            <span class="eyebrow block">{{ $conv->isDirect() ? 'Persönlich' : 'Austausch in der Gruppe' }}</span>
            <span class="block truncate" style="font-family:var(--font-heading);font-size:20px;line-height:1.3">{{ $conv->isDirect() ? 'Gespräch mit '.$gegenueber : $gegenueber }}</span>
        </span>
    </div>

    @if ($conv->isDirect() && ! App\Coach\Ansicht::teamSicht($ich) && ($kontingent || $buchen || $naechster))
        <div class="karte" style="margin-bottom:12px">
            @if ($kontingent)
                <span class="eyebrow"><i class="fa-solid fa-ticket"></i> Deine Sitzungen</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <b style="font-family:var(--font-heading);font-size:26px;font-weight:400">{{ $kontingent['offen'] }}</b>
                    <span class="x">von {{ $kontingent['gesamt'] }} noch offen{{ $kontingent['geplant'] ? ', '.$kontingent['geplant'].' geplant' : '' }}</span>
                </div>
            @else
                <span class="eyebrow"><i class="fa-solid fa-user-group"></i> 1:1 mit {{ $gegenueber }}</span>
                <p class="x m-0 mt-1">Eine Stunde nur für dich und dein Thema. Einzeln oder als Paket.</p>
            @endif
            <div class="flex flex-wrap gap-2 mt-3">
                @if ($naechster)<a href="{{ route('termine.show', $naechster) }}" class="knopf knopf-ruhig knopf-klein"><i class="fa-regular fa-calendar"></i>{{ \App\Support\Zeit::wannKurz($naechster->starts_at) }}</a>@endif
                @if ($buchen)<a href="{{ route('buchen.index') }}" class="knopf knopf-klein"><i class="fa-solid fa-calendar-plus"></i>{{ $kontingent ? 'Sitzung buchen' : 'Gespräch buchen' }}</a>@endif
            </div>
        </div>
    @endif

    </div>

    <div id="verlauf" class="flex flex-col gap-2" data-verlauf="{{ route('gespraech.neu', $conv) }}" data-kanal="gespraech.{{ $conv->id }}" data-letzte="{{ $messages->last()?->id ?? 0 }}" data-ich="{{ $ich->id }}">
        @if ($versteckt)
            <a href="{{ route('gespraech.show', [$conv, 'alle' => 1]) }}" class="knopf knopf-leise self-center knopf-klein">{{ $versteckt }} ältere Nachrichten zeigen</a>
        @endif
        @php $trenner = false; @endphp
        @forelse ($messages as $m)
            @if (! $trenner && $neuAb && $m->created_at->gt($neuAb) && $m->user_id !== $ich->id)
                @php $trenner = true; @endphp
                <div class="neu-trenner" data-neu-trenner><span>Neu</span></div>
            @endif
            @include('gespraech._nachricht', ['m' => $m, 'neu' => $trenner])
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
            <div class="flex flex-wrap items-center gap-2" data-probe hidden>
                <span class="hinweis w-full m-0">Hör kurz rein, dann schick sie ab.</span>
                <audio controls preload="metadata" class="min-w-0 flex-1" style="height:40px;max-width:280px" data-probe-audio></audio>
                <span class="hinweis" data-probe-dauer></span>
                <button type="button" class="knopf knopf-klein" data-probe-senden><i class="fa-solid fa-paper-plane"></i>Senden</button>
                <button type="button" class="knopf knopf-text knopf-klein" data-probe-weg><i class="fa-solid fa-trash"></i>Verwerfen</button>
            </div>
            <div data-chat-anhang @unless (request()->query('ref')) hidden @endunless>
                <x-anhang-wahl :mehrfach="false" :refs="array_values(array_filter([request()->query('ref')], fn ($r) => is_string($r) && preg_match('~^[a-z]+:\d+$~', $r)))">
                    <label class="knopf knopf-leise knopf-klein" style="cursor:pointer"><i class="fa-solid fa-camera"></i>Foto oder Datei<input type="file" name="file" class="hidden" accept="image/*,application/pdf,audio/*"></label>
                </x-anhang-wahl>
            </div>
            <div class="chat-knoepfe">
                <button type="button" class="chat-rund" data-anhang-plus title="Foto, Datei oder etwas aus der App anhängen" aria-label="Anhängen"><i class="fa-solid fa-plus"></i></button>
                <button type="button" class="chat-rund" data-diktat title="Diktieren: Gesprochenes wird zu Text" aria-label="Diktieren"><i class="fa-solid fa-microphone"></i></button>
                <button type="button" class="chat-rund" data-sprache title="Sprachnachricht aufnehmen" aria-label="Sprachnachricht aufnehmen"><span class="punkt"></span></button>
                <span class="flex-1"></span>
                <button type="submit" class="chat-senden" title="Senden" aria-label="Senden"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>window.CHAT = { emojis: @json(collect(\App\Models\Reaction::EMOJIS)->map(fn ($e) => $e[0])) };</script>
    @endpush
</x-layouts.app>

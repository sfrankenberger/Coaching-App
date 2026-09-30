<x-layouts.app title="Angebote">
    <h1>Angebote</h1>
    <p class="unterzeile m-0 mb-4">Was es gibt, alles an einem Ort.</p>
    @forelse ($angebote as $o)
        @php $preise = $o->preise(); $w = array_key_first($preise) ?? 'CHF'; @endphp
        <article class="karte" style="margin-bottom:12px">
            @if ($o->settings['bild_url'] ?? null)<img src="{{ $o->settings['bild_url'] }}" alt="" style="width:100%;border-radius:12px;margin-bottom:10px;aspect-ratio:16/9;object-fit:cover">@endif
            <span class="eyebrow">{{ \App\Models\Offer::TYPES[$o->type] ?? 'Angebot' }}</span>
            <h2 class="m-0 mb-1" style="font-family:var(--font-heading);font-size:20px;font-weight:400">{{ $o->title }}</h2>
            @if ($o->settings['teaser'] ?? null)<p class="lesetext m-0 mb-2">{{ $o->settings['teaser'] }}</p>@endif
            <div class="flex items-center gap-3 flex-wrap">
                <b>{{ $o->is_free ? 'Kostenlos' : $o->preisMitIntervall($preise[$w], $w) }}</b>
                @if (in_array($o->id, $meine, true))
                    <span class="chip chip-ok">Hast du schon</span>
                @else
                    <a href="{{ $o->kaufUrl('app') }}" class="knopf knopf-klein ml-auto">{{ $o->is_free ? 'Dabei sein' : 'Kaufen' }}</a>
                @endif
            </div>
        </article>
    @empty
        <x-leer icon="tag">Gerade gibt es nichts Neues.</x-leer>
    @endforelse
</x-layouts.app>

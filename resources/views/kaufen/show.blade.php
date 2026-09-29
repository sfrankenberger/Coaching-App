<x-layouts.app :title="$offer->title" :schmal="true" body="ohne-leiste">
    @if ($offer->settings['bild_url'] ?? null)
        <img src="{{ $offer->settings['bild_url'] }}" alt="" style="width:100%;border-radius:18px;margin:6px 0 14px;aspect-ratio:16/9;object-fit:cover">
    @endif
    <span class="eyebrow">{{ \App\Models\Offer::TYPES[$offer->type] ?? 'Angebot' }}</span>
    <h1 class="m-0 mb-2">{{ $offer->title }}</h1>
    @if ($offer->settings['teaser'] ?? null)<p class="lesetext m-0 mb-4">{{ $offer->settings['teaser'] }}</p>@endif

    <div class="karte">
        <div class="flex items-baseline gap-3 flex-wrap">
            @if ($offer->is_free)
                <b style="font-family:var(--font-heading);font-size:28px;font-weight:400">Kostenlos</b>
            @elseif ($preis !== null)
                <b style="font-family:var(--font-heading);font-size:28px;font-weight:400">{{ \App\Models\Offer::preisText($preis, $waehrung) }}</b>
                @if ($regulaer && $regulaer > $preis)<span class="hinweis" style="text-decoration:line-through">{{ \App\Models\Offer::preisText($regulaer, $waehrung) }}</span><span class="chip chip-ok">Aktion{{ $offer->settings['aktion_bis'] ? ' bis '.\Carbon\Carbon::parse($offer->settings['aktion_bis'])->translatedFormat('j. F') : '' }}</span>@endif
            @endif
            @if (count($preise) > 1)
                <span class="flex gap-1 ml-auto">
                    @foreach ($preise as $w => $p)<a href="{{ route('kaufen', array_filter(['angebot' => $offer->slug, 'w' => $w, 'ref' => $ref])) }}" @class(['pille', 'an' => $w === $waehrung])>{{ $w }}</a>@endforeach
                </span>
            @endif
        </div>
        @if ($offer->access_days)<p class="hinweis m-0 mt-1">Zugang für {{ $offer->access_days }} Tage</p>@endif
        @if ($offer->programs->isNotEmpty())<p class="hinweis m-0 mt-1">Dabei: {{ $offer->programs->pluck('title')->join(', ') }}</p>@endif
    </div>

    @if ($hat)
        <x-leer icon="circle-check" knopf="Zu deinen Kursen" :href="route('kurse.index')">Das hast du schon. Dein Zugang ist offen.</x-leer>
    @elseif (! $offer->is_free && ! $rechnung)
        <x-leer icon="envelope">Die Bezahlung online kommt bald. Schreib uns kurz, dann bekommst du den Zugang von Hand.</x-leer>
    @else
        <form method="post" action="{{ route('kaufen.store', $offer->slug) }}" class="karte mt-3">
            @csrf
            <input type="hidden" name="waehrung" value="{{ $waehrung }}">
            <input type="hidden" name="ref" value="{{ $ref }}">
            <input type="hidden" name="zahlung" value="{{ $offer->is_free ? 'gratis' : 'rechnung' }}">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            @if ($person)
                <p class="m-0 mb-3 text-md">Du bist angemeldet als <b>{{ $person->name }}</b> ({{ $person->email }}).</p>
            @else
                <label class="block mb-3"><span class="feld-label">Dein Name</span><input type="text" name="name" class="feld" required maxlength="120" value="{{ old('name') }}" autocomplete="name"></label>
                <label class="block mb-3"><span class="feld-label">Deine E-Mail</span><input type="email" name="email" class="feld" required maxlength="190" value="{{ old('email') }}" autocomplete="email"></label>
                @error('email')<p class="fehler mb-2">{{ $message }}</p>@enderror
            @endif
            @unless ($offer->is_free)
                <p class="hinweis mb-3"><i class="fa-regular fa-file-lines"></i> Du bekommst die Rechnung per Mail, zahlbar innert 30 Tagen, auch online per Karte oder Twint. Dein Zugang {{ ($zugangSofort ?? true) ? 'ist sofort offen' : 'öffnet sich mit der Zahlung' }}.</p>
            @endunless
            <label class="flex items-start gap-2 mb-3 text-md"><input type="checkbox" name="agb" value="1" required class="mt-1"> <span>Ich bestelle zahlungspflichtig und bin einverstanden, dass es sofort losgeht.</span></label>
            @error('agb')<p class="fehler mb-2">{{ $message }}</p>@enderror
            <button type="submit" class="knopf" style="width:100%"><i class="fa-solid fa-cart-plus"></i>{{ $offer->is_free ? 'Kostenlos dabei sein' : 'Auf Rechnung kaufen' }}</button>
        </form>
    @endif
</x-layouts.app>

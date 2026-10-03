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
                <b style="font-family:var(--font-heading);font-size:28px;font-weight:400">{{ \App\Models\Offer::preisText($preis, $waehrung) }}</b>@if ($offer->istAbo())<span class="hinweis">{{ $offer->intervallText() }}, jederzeit kündbar</span>@endif
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
    @elseif (! $offer->is_free && ! $rechnung && ! $stripe)
        <x-leer icon="envelope">Die Kasse ist gerade nicht bereit. Schreib uns kurz, dann bekommst du den Zugang von Hand.</x-leer>
    @else
        @if (session('fehler'))<p class="fehler mb-2">{{ session('fehler') }}</p>@endif
        @if (request()->boolean('abbruch'))<p class="hinweis mb-2">Die Zahlung wurde abgebrochen. Du kannst es jederzeit nochmal versuchen.</p>@endif
        <form method="post" action="{{ route('kaufen.store', $offer->slug) }}" class="karte mt-3">
            @csrf
            <input type="hidden" name="waehrung" value="{{ $waehrung }}">
            <input type="hidden" name="ref" value="{{ $ref }}">
            @if ($offer->is_free)
                <input type="hidden" name="zahlung" value="gratis">
            @elseif ($stripe && $rechnung)
                <span class="feld-label">Wie möchtest du bezahlen?</span>
                <label class="flex items-center gap-2 mb-1 text-md"><input type="radio" name="zahlung" value="stripe" class="accent-primary" @checked(old('zahlung', 'stripe') === 'stripe')> Karte oder Twint, sofort{{ $offer->istAbo() ? ', danach '.$offer->intervallText().' automatisch' : '' }}</label>
                <label class="flex items-center gap-2 mb-3 text-md"><input type="radio" name="zahlung" value="rechnung" class="accent-primary" @checked(old('zahlung') === 'rechnung')> Auf Rechnung, zahlbar innert 30 Tagen</label>
            @else
                <input type="hidden" name="zahlung" value="{{ $stripe ? 'stripe' : 'rechnung' }}">
            @endif
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            @if ($person)
                <p class="m-0 mb-3 text-md">Du bist angemeldet als <b>{{ $person->name }}</b> ({{ $person->email }}).</p>
            @else
                <label class="block mb-3"><span class="feld-label">Dein Name</span><input type="text" name="name" class="feld" required maxlength="120" value="{{ old('name') }}" autocomplete="name"></label>
                <label class="block mb-3"><span class="feld-label">Deine E-Mail</span><input type="email" name="email" class="feld" required maxlength="190" value="{{ old('email') }}" autocomplete="email"></label>
                @error('email')<p class="fehler mb-2">{{ $message }}</p>@enderror
            @endif
            @unless ($offer->is_free)
                <span class="feld-label">Rechnungsadresse</span>
                <label class="block mb-2"><input type="text" name="strasse" class="feld" required maxlength="160" placeholder="Strasse und Nummer" value="{{ old('strasse', $adresse['strasse'] ?? '') }}" autocomplete="street-address"></label>
                <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 2fr 1fr">
                    <input type="text" name="plz" class="feld" required maxlength="12" placeholder="PLZ" value="{{ old('plz', $adresse['plz'] ?? '') }}" autocomplete="postal-code">
                    <input type="text" name="ort" class="feld" required maxlength="120" placeholder="Ort" value="{{ old('ort', $adresse['ort'] ?? '') }}" autocomplete="address-level2">
                    <select name="land" class="feld" autocomplete="country">@foreach (['CH' => 'Schweiz', 'DE' => 'Deutschland', 'AT' => 'Österreich', 'LI' => 'Liechtenstein', 'FR' => 'Frankreich', 'IT' => 'Italien'] as $k => $l)<option value="{{ $k }}" @selected(old('land', $adresse['land'] ?? 'CH') === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                @error('strasse')<p class="fehler mb-2">{{ $message }}</p>@enderror
                @if ($offer->istAbo())<p class="hinweis mb-3"><i class="fa-solid fa-rotate"></i> Das Abo verlängert sich {{ $offer->intervallText() }} von selbst. Du kannst es jederzeit in deinem Profil kündigen, dann läuft es zum Ende der bezahlten Zeit aus.</p>@endif
                <p class="hinweis mb-3"><i class="fa-regular fa-file-lines"></i> Die Rechnung oder Quittung kommt per Mail. Dein Zugang {{ ($zugangSofort ?? true) ? 'ist sofort offen' : 'öffnet sich mit der Zahlung' }}.</p>
            @endunless
            <label class="flex items-start gap-2 mb-2 text-md"><input type="checkbox" name="agb" value="1" required class="mt-1"> <span>Ich bestelle {{ $offer->is_free ? '' : 'zahlungspflichtig ' }}und akzeptiere die @if (! empty($links['agb']))<a href="{{ $links['agb'] }}" target="_blank" rel="noopener">AGB</a>@else AGB @endif@if (! empty($links['datenschutz'])) und die <a href="{{ $links['datenschutz'] }}" target="_blank" rel="noopener">Datenschutzerklärung</a>@endif.</span></label>
            @error('agb')<p class="fehler mb-2">{{ $message }}</p>@enderror
            <label class="flex items-start gap-2 mb-2 text-md"><input type="checkbox" name="newsletter" value="1" class="mt-1" @checked(old('newsletter'))> <span>Ja, ich möchte den Newsletter von {{ $coach ?? app(\App\Tenancy\Branding::class)->coachName() }}: Impulse und Neues, ab und zu, jederzeit abbestellbar.</span></label>
            @unless ($offer->is_free)
                <label class="flex items-start gap-2 mb-3 text-md"><input type="checkbox" name="widerruf" value="1" required class="mt-1"> <span>Ich möchte, dass es sofort losgeht, und weiss, dass ich damit auf mein Widerrufsrecht verzichte.@if (! empty($links['widerruf'])) <a href="{{ $links['widerruf'] }}" target="_blank" rel="noopener">Widerrufsbelehrung</a>@endif</span></label>
                @error('widerruf')<p class="fehler mb-2">{{ $message }}</p>@enderror
            @endunless
            <button type="submit" class="knopf" style="width:100%"><i class="fa-solid fa-{{ $offer->is_free ? 'cart-plus' : ($stripe ? 'credit-card' : 'file-lines') }}"></i>{{ $offer->is_free ? 'Kostenlos dabei sein' : ($stripe && $rechnung ? 'Verbindlich kaufen' : ($stripe ? 'Jetzt bezahlen' : 'Auf Rechnung kaufen')) }}</button>
            @if (! empty($links['impressum']))<p class="hinweis m-0 mt-2 text-center"><a href="{{ $links['impressum'] }}" target="_blank" rel="noopener">Impressum</a></p>@endif
        </form>
    @endif
</x-layouts.app>

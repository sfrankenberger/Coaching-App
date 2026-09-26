{{-- Eine Trefferkarte im Nachschlagen. Erwartet $k (Fundus::karte), $gemerkt, $coach --}}
<article @class(['karte fu-karte', 'zu' => ! $k['offen']]) data-fu-karte="{{ $k['key'] }}" tabindex="0" role="button" aria-label="{{ $k['titel'] }} ansehen">
    <div class="fu-symbole">
        @if ($coach)
            <label class="fu-wahl" title="Für eine Sammlung auswählen"><input type="checkbox" class="sr-only" value="{{ $k['key'] }}" data-fu-wahl><span></span></label>
        @endif
        <x-merken :art="$k['art']" :id="$k['id']" :an="$gemerkt->has($k['key'])" />
        <button type="button" class="merken fu-teilen" data-fu-teilen="{{ $k['key'] }}" title="Teilen" aria-label="Teilen"><i class="fa-solid fa-share-nodes"></i></button>
    </div>
    <div class="fu-in">
        <span class="eyebrow"><i class="fa-solid fa-{{ $k['icon'] }}"></i> {{ in_array($k['art'], ['unit', 'step'], true) ? 'Kurs' : $k['label'] }}</span>
        <h3 class="fu-titel">
            @if ($k['offen'] && $k['url'])<a href="{{ $k['url'] }}">{{ $k['titel'] }}</a>@else{{ $k['titel'] }}@endif
        </h3>
        @if ($k['fundort'])<p class="fu-fundort">{{ $k['fundort'] }}</p>@endif
        @if ($k['kurz'])<p class="fu-kurz">{{ $k['kurz'] }}</p>@endif
        @if ($k['hilft'])<p class="fu-hilft">{{ $k['hilft'] }}</p>@endif
        @if ($k['themen'])<p class="fu-themen">{{ implode(' · ', $k['themen']) }}</p>@endif
        @if ($k['warum'])<p class="fu-warum"><i class="fa-solid fa-arrow-turn-down"></i> {{ $k['warum'] }}</p>@endif
        @if ($k['tuer'])<p class="fu-tuer"><i class="fa-solid fa-lock"></i> {{ $k['tuer']['text'] }} <a href="{{ $k['tuer']['url'] }}">{{ $k['tuer']['knopf'] }}</a></p>@endif
    </div>
</article>

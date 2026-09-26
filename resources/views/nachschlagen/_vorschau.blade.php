{{-- Vorschau im Fenster. Erwartet $k (Fundus::vorschau), $gemerkt, $coach, $leute --}}
<div class="fu-vorschau" data-fu-vorschau="{{ $k['key'] }}" data-link="{{ $k['url'] }}" data-titel="{{ $k['titel'] }}">
    <span class="eyebrow"><i class="fa-solid fa-{{ $k['icon'] }}"></i> {{ in_array($k['art'], ['unit', 'step'], true) ? 'Kurs' : $k['label'] }}</span>
    <h3>{{ $k['titel'] }}</h3>
    @if ($k['fundort'])<p class="fu-fundort">{{ $k['fundort'] }}</p>@endif
    @if ($k['kurz'])<p class="fu-kurz">{{ $k['kurz'] }}</p>@endif
    @if ($k['hilft'])<p class="fu-hilft">{{ $k['hilft'] }}</p>@endif
    @if ($k['anriss'] && $k['anriss'] !== $k['kurz'])<p class="fu-anriss lesetext">{{ $k['anriss'] }}</p>@endif
    @if ($k['themen'])<p class="fu-themen">{{ implode(' · ', $k['themen']) }}</p>@endif
    @if ($k['tuer'])<p class="fu-tuer"><i class="fa-solid fa-lock"></i> {{ $k['tuer']['text'] }}</p>@endif
    <div class="fu-vtun">
        @if ($k['url'])<a class="knopf" href="{{ $k['url'] }}">{{ $k['knopf'] }}</a>@endif
        <x-merken :art="$k['art']" :id="$k['id']" :an="$gemerkt->has($k['key'])" :text="true" />
        <button type="button" class="knopf knopf-leise knopf-klein" data-fu-link><i class="fa-solid fa-share-nodes"></i><span>Link teilen</span></button>
    </div>
    @if ($coach && $leute->isNotEmpty())
        <div class="fu-schicken" data-fu-schicken>
            <p class="eyebrow">In ein Gespräch schicken</p>
            <div class="fu-wer">
                @foreach ($leute as $p)
                    <label class="fu-pers"><input type="checkbox" value="{{ $p['id'] }}" data-fu-an> {{ $p['name'] }}</label>
                @endforeach
            </div>
            <textarea class="feld" rows="2" placeholder="Ein Satz dazu, warum du das schickst" data-fu-gruss data-ohne-diktat></textarea>
            <button type="button" class="knopf knopf-klein" data-fu-senden="{{ route('nachschlagen.teilen') }}">In den Chat schicken</button>
            <p class="hinweis fu-stand" data-fu-stand></p>
        </div>
    @endif
</div>

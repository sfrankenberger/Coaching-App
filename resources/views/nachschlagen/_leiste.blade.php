{{-- Leiste zum Teilen, erscheint sobald die Coachin etwas ausgewaehlt hat --}}
<div class="fu-leiste" data-fu-leiste data-senden="{{ route('nachschlagen.teilen') }}" aria-hidden="true">
    <div class="fu-leiste-kopf"><span><b data-fu-zahl>0</b> ausgewählt</span><button type="button" class="sheet-zu" aria-label="Auswahl leeren" data-fu-leeren>&times;</button></div>
    <label class="block"><span class="eyebrow">Name der Sammlung</span><input type="text" class="feld" placeholder="Zum Beispiel: Für Nicole, Gedankensturm" data-fu-name></label>
    <label class="block mt-2"><span class="eyebrow">Dein Gruss dazu</span><textarea class="feld" rows="2" placeholder="Ein, zwei Sätze, warum du das schickst" data-fu-leiste-gruss data-ohne-diktat></textarea></label>
    @if ($leute->isNotEmpty())
        <p class="eyebrow mt-2 mb-1">An wen?</p>
        <div class="fu-wer">
            @foreach ($leute as $p)
                <label class="fu-pers"><input type="checkbox" value="{{ $p['id'] }}" data-fu-leiste-an> {{ $p['name'] }}</label>
            @endforeach
        </div>
    @endif
    <div class="flex items-center gap-2 mt-3 flex-wrap">
        <button type="button" class="knopf" data-fu-leiste-senden>Schicken</button>
        <button type="button" class="knopf knopf-leise" data-fu-leiste-link>Nur Link erzeugen</button>
    </div>
    <p class="hinweis mt-2 m-0" data-fu-leiste-stand></p>
</div>

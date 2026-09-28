<x-layouts.app title="Gebucht" :schmal="true">
    <x-karte>
        <span class="eyebrow">{{ $gast['titel'] }}</span>
        <h1 class="m-0 mt-1">Gebucht, ich freue mich auf dich</h1>
        <p class="x m-0 mt-2" style="font-size:var(--fs-lg)">{{ $gast['wann'] }} Uhr</p>
        <p class="lesetext mt-3">Eine Bestätigung ist unterwegs an <b>{{ $gast['email'] }}</b>. Darin ist ein Knopf in die App: dort siehst du den Termin, kannst ihn verschieben oder absagen, und findest schon jetzt Impulse und den Podcast.</p>
        <p class="hinweis m-0">Keine Mail? Schau im Spam-Ordner nach oder schreib mir.</p>
    </x-karte>
</x-layouts.app>

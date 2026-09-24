<x-layouts.app title="Merkliste">
    <h1 class="mb-1">Merkliste</h1>
    <p class="text-ink-soft mb-3">Alles, was du dir gemerkt hast. Tippe auf das Lesezeichen, um etwas wieder zu entfernen.</p>

    @forelse ($zeilen as $z)
        <x-inhalt-zeile :z="$z" :gemerkt="$gemerkt" />
    @empty
        <x-karte><p class="text-ink-soft">Noch nichts gemerkt. Bei Material, Terminen, Einheiten, Impulsen und Podcastfolgen findest du ein Lesezeichen. Was du dort markierst, landet hier.</p></x-karte>
    @endforelse
</x-layouts.app>

<x-layouts.app title="Merkliste">
    <h1 class="mb-1">Merkliste</h1>
    <p class="unterzeile m-0 mb-3.5">Alles, was du dir gemerkt hast. Tippe auf das Lesezeichen, um etwas wieder zu entfernen.</p>

    @forelse ($zeilen as $z)
        <x-inhalt-zeile :z="$z" :gemerkt="$gemerkt" />
    @empty
        <x-leer icon="bookmark" knopf="Zu den Impulsen" :href="route('impulse.index')">Noch nichts gemerkt. Bei Material, Terminen, Einheiten, Impulsen und Podcastfolgen findest du ein Lesezeichen. Was du dort markierst, landet hier.</x-leer>
    @endforelse
</x-layouts.app>

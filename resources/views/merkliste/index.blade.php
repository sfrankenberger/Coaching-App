<x-layouts.app title="Merkliste">
    <h1 style="margin-bottom:4px">Merkliste</h1>
    <p class="unterzeile" style="margin:0 0 14px">Alles, was du dir gemerkt hast. Tippe auf das Lesezeichen, um etwas wieder zu entfernen.</p>

    @forelse ($zeilen as $z)
        <x-inhalt-zeile :z="$z" :gemerkt="$gemerkt" />
    @empty
        <x-leer icon="bookmark" knopf="Zu den Impulsen" :href="route('impulse.index')">Noch nichts gemerkt. Bei Material, Terminen, Einheiten, Impulsen und Podcastfolgen findest du ein Lesezeichen. Was du dort markierst, landet hier.</x-leer>
    @endforelse
</x-layouts.app>

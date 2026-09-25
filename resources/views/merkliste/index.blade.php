<x-layouts.app title="Merkliste">
    <h1 style="margin-bottom:4px">Merkliste</h1>
    <p class="unterzeile" style="margin:0 0 14px">Alles, was du dir gemerkt hast. Tippe auf das Lesezeichen, um etwas wieder zu entfernen.</p>

    @forelse ($zeilen as $z)
        <x-inhalt-zeile :z="$z" :gemerkt="$gemerkt" />
    @empty
        <div class="leer"><i class="fa-regular fa-bookmark"></i>Noch nichts gemerkt. Bei Material, Terminen, Einheiten, Impulsen und Podcastfolgen findest du ein Lesezeichen. Was du dort markierst, landet hier.</div>
    @endforelse
</x-layouts.app>

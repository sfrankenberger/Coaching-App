<x-layouts.app title="Mitteilungen">
    <h1>Mitteilungen</h1>
    <p class="unterzeile m-0 mb-4">Alles, was dich erreicht hat: Nachrichten, Termine, Aufzeichnungen, Aufgaben.</p>
    @forelse ($liste as $m)
        <a href="{{ route('mitteilungen.oeffnen', $m->id) }}" @class(['zeile', 'neu' => $neu->has($m->id)])>
            <span class="ic"><i class="fa-solid fa-{{ $m->icon() }}"></i></span>
            <span class="tx">
                <b>{{ $m->titel() }}</b>
                <span>{{ \Illuminate\Support\Str::limit($m->text(), 120) }}</span>
                <span class="hinweis">{{ \App\Support\Zeit::relativ($m->created_at) }}</span>
            </span>
            <i class="fa-solid fa-chevron-right pf"></i>
        </a>
    @empty
        <x-leer icon="bell">Noch keine Mitteilungen. Sobald etwas für dich da ist, siehst du es hier und an der Glocke oben.</x-leer>
    @endforelse
</x-layouts.app>

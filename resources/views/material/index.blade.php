<x-layouts.app title="Material">
    <h1 class="mb-3">Material</h1>

    <div class="flex flex-wrap gap-2 mb-2">
        <a href="{{ route('material.index') }}" @class(['knopf', 'knopf-leise' => $filter !== ''])" style="min-height:36px;padding:6px 14px">Alles</a>
        @foreach ($kurse as $k)
            <a href="{{ route('material.index', ['f' => 'k'.$k->id]) }}" @class(['knopf', 'knopf-leise' => $filter !== 'k'.$k->id])" style="min-height:36px;padding:6px 14px">{{ $k->title }}</a>
        @endforeach
        <a href="{{ route('material.index', ['f' => 'aufzeichnung']) }}" @class(['knopf', 'knopf-leise' => $filter !== 'aufzeichnung'])" style="min-height:36px;padding:6px 14px">Aufzeichnungen</a>
        <a href="{{ route('material.index', ['f' => 'gemerkt']) }}" @class(['knopf', 'knopf-leise' => $filter !== 'gemerkt'])" style="min-height:36px;padding:6px 14px">Gemerkt{{ $gemerkt->count() ? ' ('.$gemerkt->count().')' : '' }}</a>
    </div>
    <form method="get" class="mb-3">
        @if ($filter) <input type="hidden" name="f" value="{{ $filter }}"> @endif
        <input type="search" name="q" value="{{ $suche }}" class="feld" placeholder="Im Material suchen">
    </form>

    @forelse ($zeilen as $z)
        @php $key = $z['art'].'-'.$z['id']; @endphp
        <article class="karte flex items-center gap-3">
            <span class="hinweis uppercase w-12 shrink-0 text-center text-xs font-semibold">{{ $z['typ'] === 'aufzeichnung' ? 'Video' : (\App\Models\Resource::TYPES[$z['typ']] ?? $z['typ']) }}</span>
            <div class="min-w-0 flex-1">
                <span class="block text-base leading-snug">{{ $z['titel'] }}</span>
                <span class="hinweis block">
                    @if ($z['kurs'] && ($k = $kurse->firstWhere('id', $z['kurs']))) {{ $k->title }} · @endif
                    @if ($z['geteilt']) Für dich geteilt · @endif
                    {{ $z['ts']?->translatedFormat('j. M Y') }}
                    @if ($z['dauer']) · {{ $z['dauer'] }} @endif
                </span>
                @if ($z['text'] && $z['art'] === 'resource')
                    <p class="text-md text-ink-soft mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($z['text']), 160) }}</p>
                @endif
            </div>
            <form method="post" action="{{ route('merken') }}" data-merken>
                @csrf
                <input type="hidden" name="type" value="{{ $z['art'] }}"><input type="hidden" name="id" value="{{ $z['id'] }}">
                <button type="submit" class="size-9 grid place-items-center rounded-full border border-line {{ $gemerkt->has($key) ? 'text-primary border-primary' : 'text-muted' }}" aria-label="Merken" title="Merken">
                    <svg viewBox="0 0 24 24" fill="{{ $gemerkt->has($key) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" class="size-4"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
                </button>
            </form>
            @if ($z['url'])
                <a href="{{ $z['url'] }}" @if ($z['art'] === 'resource') target="_blank" rel="noopener" @endif class="knopf knopf-leise" style="min-height:36px;padding:6px 12px">{{ $z['art'] === 'event' ? 'Ansehen' : 'Öffnen' }}</a>
            @endif
        </article>
    @empty
        <x-karte><p class="text-ink-soft">{{ $filter === 'gemerkt' ? 'Noch nichts gemerkt. Tippe bei einer Karte auf das Lesezeichen, dann findest du sie hier wieder.' : 'Nichts in dieser Auswahl.' }}</p></x-karte>
    @endforelse
</x-layouts.app>

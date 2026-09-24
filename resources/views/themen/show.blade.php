<x-layouts.app :title="$thema->name">
    <p class="mb-2"><a href="{{ route('themen.index') }}" class="hinweis no-underline">&larr; Themen</a></p>
    <h1 class="mb-1">{{ $thema->name }}</h1>
    @if ($thema->description)<p class="text-ink-soft mb-3">{{ $thema->description }}</p>@endif

    @forelse ($gruppen as $art => $zeilen)
        <h2 class="mt-4 mb-1">{{ \App\Content\Inhalte::ARTEN[$art] ?? $art }}{{ $zeilen->count() > 1 ? 'e' : '' }}</h2>
        @foreach ($zeilen as $z)
            @php $f = $z['model']->finder ?? null; @endphp
            <article class="karte flex items-start gap-3">
                @if ($z['bild'])
                    <a href="{{ $z['url'] }}" class="shrink-0"><img src="{{ $z['bild'] }}" alt="" class="size-16 rounded-lg object-cover bg-page" loading="lazy"></a>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ $z['url'] }}" class="block text-base leading-snug no-underline text-ink">{{ $z['titel'] }}</a>
                    <span class="hinweis block">{{ $z['typ'] }}@if ($z['ts']) · {{ $z['ts']->translatedFormat('j. M Y') }}@endif</span>
                    @if ($f?->summary)
                        <p class="text-md text-ink-soft mt-1">{{ $f->summary }}</p>
                        @if ($f->helps)<p class="hinweis mt-1"><em>{{ $f->helps }}</em></p>@endif
                    @elseif ($z['text'])
                        <p class="text-md text-ink-soft mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($z['text']), 160) }}</p>
                    @endif
                </div>
                <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($z['art'].'-'.$z['id'])" />
            </article>
        @endforeach
    @empty
        <x-karte><p class="text-ink-soft">Zu diesem Thema ist für dich gerade nichts freigeschaltet.</p></x-karte>
    @endforelse
</x-layouts.app>

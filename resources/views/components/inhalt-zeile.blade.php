@props(['z', 'gemerkt'])
@php $key = $z['art'].'-'.$z['id']; @endphp
<article class="karte flex items-center gap-3">
    @if ($z['bild'])
        <a href="{{ $z['url'] }}" class="shrink-0"><img src="{{ $z['bild'] }}" alt="" class="size-16 rounded-lg object-cover bg-page" loading="lazy"></a>
    @else
        <span class="hinweis uppercase w-12 shrink-0 text-center text-xs font-semibold">{{ \Illuminate\Support\Str::before($z['typ'], ' ·') }}</span>
    @endif
    <div class="min-w-0 flex-1">
        <a href="{{ $z['url'] }}" class="block text-base leading-snug no-underline text-ink">{{ $z['titel'] }}</a>
        <span class="hinweis block">{{ $z['typ'] }}@if ($z['ts']) · {{ $z['ts']->translatedFormat('j. M Y') }}@endif</span>
        @if ($z['text'])
            <p class="text-md text-ink-soft mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($z['text']), 160) }}</p>
        @endif
    </div>
    <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($key)" />
</article>

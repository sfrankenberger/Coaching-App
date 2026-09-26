<x-layouts.app :title="$post->title">
    <p class="m-0 mb-2"><a href="{{ route('impulse.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Impulse</a></p>

    <x-karte class="!p-0 overflow-hidden">
        @if ($post->image_url)
            <img src="{{ $post->image_url }}" alt="" class="w-full max-h-80 object-cover">
        @endif
        <div class="p-4">
            <span class="eyebrow">{{ $post->typeLabel() }}@if ($post->published_at) · {{ $post->published_at->translatedFormat('j. F Y') }}@endif</span>
            <h1 class="mt-1">{{ $post->title }}</h1>
            @if ($post->categories)
                <p class="hinweis mt-1">{{ implode(' · ', $post->categories) }}</p>
            @endif
            <div class="prose-app mt-3">{!! $post->bodyFor(auth()->user()) !!}</div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-merken art="post" :id="$post->id" :an="$gemerkt->has('post-'.$post->id)" :text="true" />
                @if ($post->url)
                    <a href="{{ $post->url }}" target="_blank" rel="noopener" class="knopf knopf-leise knopf-klein">Im Web öffnen</a>
                @endif
            </div>
        </div>
    </x-karte>

    @if ($post->topics->isNotEmpty())
        <x-karte titel="Themen" icon="tag">
            <div class="flex flex-wrap gap-2">
                @foreach ($post->topics as $t)
                    <a href="{{ route('themen.show', $t) }}" class="chip no-underline"><i class="fa-solid fa-tag"></i>{{ $t->name }}</a>
                @endforeach
            </div>
        </x-karte>
    @endif
</x-layouts.app>

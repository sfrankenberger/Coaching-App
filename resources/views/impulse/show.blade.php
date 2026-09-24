<x-layouts.app :title="$post->title">
    <p class="mb-2"><a href="{{ route('impulse.index') }}" class="hinweis no-underline">&larr; Impulse</a></p>

    <x-karte class="!p-0 overflow-hidden">
        @if ($post->image_url)
            <img src="{{ $post->image_url }}" alt="" class="w-full max-h-80 object-cover">
        @endif
        <div class="p-4">
            <span class="hinweis uppercase tracking-wider text-xs font-semibold">{{ $post->typeLabel() }}@if ($post->published_at) · {{ $post->published_at->translatedFormat('j. F Y') }}@endif</span>
            <h1 class="mt-1">{{ $post->title }}</h1>
            @if ($post->categories)
                <p class="hinweis mt-1">{{ implode(' · ', $post->categories) }}</p>
            @endif
            <div class="prose-app mt-3">{!! $post->body ?: nl2br(e($post->excerpt)) !!}</div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-merken art="post" :id="$post->id" :an="$gemerkt->has('post-'.$post->id)" :text="true" />
                @if ($post->url)
                    <a href="{{ $post->url }}" target="_blank" rel="noopener" class="knopf knopf-leise" style="min-height:36px;padding:6px 12px">Im Web öffnen</a>
                @endif
            </div>
        </div>
    </x-karte>

    @if ($post->topics->isNotEmpty())
        <x-karte titel="Themen">
            <div class="flex flex-wrap gap-2">
                @foreach ($post->topics as $t)
                    <a href="{{ route('themen.show', $t) }}" class="knopf knopf-leise no-underline" style="min-height:32px;padding:4px 12px">{{ $t->name }}</a>
                @endforeach
            </div>
        </x-karte>
    @endif
</x-layouts.app>

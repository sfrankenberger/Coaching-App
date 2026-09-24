<x-layouts.app title="Gespräche">
    <h1 class="mb-3">Gespräche</h1>

    @forelse ($gespraeche as $g)
        <a href="{{ route('gespraech.show', $g) }}" class="karte flex items-center gap-3 no-underline text-ink hover:border-primary">
            <span class="size-10 shrink-0 rounded-full bg-page grid place-items-center font-heading text-lg text-primary">
                {{ mb_strtoupper(mb_substr($g->isDirect() ? ($g->user?->name ?? '?') : ($g->program?->title ?? 'G'), 0, 1)) }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-base leading-snug">{{ $g->isDirect() ? ($g->user?->name ?? $g->title) : ($g->program?->title ?? $g->title) }}</span>
                <span class="hinweis">{{ $g->isDirect() ? '1:1' : 'Gruppe' }}@if ($g->last_message_at) · {{ $g->last_message_at->diffForHumans() }}@endif</span>
            </span>
            @if ($g->ungelesen)
                <span class="rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-primary-contrast">{{ $g->ungelesen }}</span>
            @endif
        </a>
    @empty
        <x-karte><p class="text-ink-soft">Noch keine Gespräche. Sobald jemand schreibt, erscheint es hier.</p></x-karte>
    @endforelse
</x-layouts.app>

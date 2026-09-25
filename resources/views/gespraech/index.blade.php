<x-layouts.app title="Gespräche">
    <h1>Gespräche</h1>

    @forelse ($gespraeche as $g)
        <a href="{{ route('gespraech.show', $g) }}" class="zeile">
            <span class="ic" style="font-family:var(--font-heading);font-size:17px">
                {{ mb_strtoupper(mb_substr($g->isDirect() ? ($g->user?->name ?? '?') : ($g->program?->title ?? 'G'), 0, 1)) }}
            </span>
            <span class="tx">
                <b>{{ $g->isDirect() ? ($g->user?->name ?? $g->title) : ($g->program?->title ?? $g->title) }}</b>
                <span>{{ $g->isDirect() ? '1:1' : 'Gruppe' }}@if ($g->last_message_at) · {{ $g->last_message_at->diffForHumans() }}@endif</span>
            </span>
            @if ($g->ungelesen)
                <span class="chip" style="background:var(--c-danger);color:#fff">{{ $g->ungelesen }}</span>
            @endif
        </a>
    @empty
        <div class="leer"><i class="fa-regular fa-comments"></i>Noch keine Gespräche. Sobald jemand schreibt, erscheint es hier.</div>
    @endforelse
</x-layouts.app>

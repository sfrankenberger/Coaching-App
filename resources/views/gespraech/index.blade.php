<x-layouts.app title="Gespräche">
    <h1>Gespräche</h1>

    @forelse ($gespraeche as $g)
        <a href="{{ route('gespraech.show', $g) }}" class="zeile">
            @if ($g->isDirect() && $g->user)
                <x-avatar :user="$g->user" :size="44" class="ic" />
            @else
                <span class="ic" style="font-family:var(--font-heading);font-size:17px">{{ mb_strtoupper(mb_substr($g->program?->title ?? 'G', 0, 1)) }}</span>
            @endif
            <span class="tx">
                <b>{{ $g->isDirect() ? ($g->user?->name ?? $g->title) : ($g->program?->title ?? $g->title) }}</b>
                <span>{{ $g->isDirect() ? '1:1' : 'Gruppe' }}@if ($g->last_message_at) · {{ $g->last_message_at->diffForHumans() }}@endif</span>
            </span>
            @if ($g->ungelesen)
                <span class="chip" style="background:var(--c-danger);color:#fff">{{ $g->ungelesen }}</span>
            @endif
        </a>
    @empty
        <x-leer icon="comments">Noch keine Gespräche. Sobald jemand schreibt, erscheint es hier.</x-leer>
    @endforelse
</x-layouts.app>

@php $videos = count($unit->videoList()); $teile = $unit->exercises?->filter(fn ($e) => $e->isAnswerable())->count() ?? 0; @endphp
<a href="{{ route('kurse.einheit', [$program, $unit]) }}" class="karte flex items-center gap-3 no-underline text-ink hover:border-primary">
    <span @class(['size-8 shrink-0 rounded-full border grid place-items-center', 'bg-success border-success text-primary-contrast' => $erledigt, 'border-line text-muted' => ! $erledigt])>
        @if ($erledigt)
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="size-4"><path d="m5 12 5 5L20 7"/></svg>
        @elseif ($unit->type === 'exercise_set')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4"><path d="M4 20h16M6 16l10-10 2 2L8 18l-3 1z"/></svg>
        @else
            <svg viewBox="0 0 24 24" fill="currentColor" class="size-3"><path d="M6 4l14 8-14 8z"/></svg>
        @endif
    </span>
    <span class="min-w-0 flex-1">
        <span class="block text-base leading-snug {{ $erledigt ? 'text-muted' : '' }}">{{ $unit->title }}</span>
        <span class="hinweis block">
            {{ \App\Models\Unit::TYPES[$unit->type] ?? '' }}
            @if ($unit->is_core) · <b>Kern</b> @endif
            @if ($videos) · {{ $videos }} {{ $videos === 1 ? 'Video' : 'Videos' }} @endif
            @if ($teile) · {{ $teile }} {{ $teile === 1 ? 'Frage' : 'Fragen' }} @endif
            @if ($unit->duration_minutes) · {{ $unit->duration_minutes }} Min. @endif
        </span>
    </span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 text-muted"><path d="m9 6 6 6-6 6"/></svg>
</a>

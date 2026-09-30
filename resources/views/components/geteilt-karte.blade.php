{{-- Ein geteilter Eintrag einer anderen Person in der Community: wer, was, Text, Anhaenge, Reaktionen, Kommentare. --}}
@props(['item'])
@php
    $g = app(\App\Coach\Geteilt::class);
    [$label, $icon] = $g->label($item);
    $typ = $item->getMorphClass();
    $ist = fn (string $k) => $item instanceof $k;
@endphp
<article id="geteilt-{{ $typ }}-{{ $item->id }}" class="karte">
    <div class="flex items-center gap-3 mb-2">
        <x-avatar :user="$item->user" :size="36" />
        <div class="min-w-0 flex-1">
            <span class="t text-md">{{ $item->user?->name ?? 'Jemand' }}</span>
            <span class="m"><i class="fa-solid fa-{{ $icon }}" style="color:var(--c-primary)"></i> {{ $label }} · {{ $item->created_at->translatedFormat('j. F, H:i') }}@if ($item->program) · <span class="chip chip-kurs" style="--kc: {{ $item->program->color ?: '#7C8C9A' }}">{{ $item->program->title }}</span>@endif</span>
        </div>
    </div>
    @if ($item instanceof \App\Models\Reflection)
        <span class="t">{{ $item->week_label ?: 'Reflexion' }}</span>
        @foreach (\App\Http\Controllers\ReflexionController::FRAGEN as $k => [$ico, $frage])
            @if ($item->$k)<p class="mt-1 mb-0"><b class="block text-md">{{ $ico }} {{ $frage }}</b><span class="lesetext whitespace-pre-line">{{ \Illuminate\Support\Str::limit($item->$k, 400) }}</span></p>@endif
        @endforeach
    @else
        @if ($item->title)<span class="t">{{ $item->title }}</span>@endif
        @if ($item->body)<p class="lesetext whitespace-pre-line m-0 mt-1">{{ \Illuminate\Support\Str::limit($item->body, 600) }}</p>@endif
        @if ($item instanceof \App\Models\Task && $item->isDone())<span class="chip chip-gut mt-1"><i class="fa-solid fa-check"></i>Erledigt</span>@endif
    @endif
    <x-anhaenge :item="$item" />
    <x-reaktionen :item="$item" :typ="$typ" />
    <x-kommentare :item="$item" />
</article>

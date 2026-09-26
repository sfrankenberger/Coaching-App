{{-- Leerer Zustand: Symbol, ein warmer Satz, auf Wunsch ein Knopf zum naechsten Schritt --}}
@props(['icon' => 'leaf', 'knopf' => null, 'href' => null])
<div {{ $attributes->merge(['class' => 'leer']) }}>
    <i class="fa-{{ str_starts_with($icon, 'solid:') ? 'solid' : 'regular' }} fa-{{ str_replace('solid:', '', $icon) }}"></i>
    <p>{{ $slot }}</p>
    @if ($knopf && $href)<a href="{{ $href }}" class="knopf knopf-klein mt-3">{{ $knopf }}</a>@endif
</div>

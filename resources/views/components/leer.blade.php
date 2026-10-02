{{-- Leerer Zustand: Symbol, ein warmer Satz, auf Wunsch ein Knopf zum naechsten Schritt --}}
@props(['icon' => 'leaf', 'knopf' => null, 'href' => null])
<div {{ $attributes->merge(['class' => 'leer']) }}>
    @php $regular = ['bell', 'bookmark', 'calendar', 'calendar-xmark', 'circle-check', 'circle-question', 'clock', 'comment', 'comments', 'envelope', 'folder-open', 'lightbulb', 'note-sticky', 'share-from-square', 'face-smile', 'star', 'heart']; @endphp
    <i class="fa-{{ in_array($icon, $regular, true) ? 'regular' : 'solid' }} fa-{{ str_replace('solid:', '', $icon) }}"></i>
    <p>{{ $slot }}</p>
    @if ($knopf && $href)<a href="{{ $href }}" class="knopf knopf-klein mt-3">{{ $knopf }}</a>@endif
</div>

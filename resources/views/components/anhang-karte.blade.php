{{-- Ein angehaengtes Element als kleine Karte mit Link. --}}
@props(['ziel'])
@php $k = $ziel ? app(\App\Support\Anhaenge::class)->karte($ziel) : null; @endphp
@if ($k)
    <a href="{{ $k['url'] ?? '#' }}" class="anhang-karte"><i class="fa-solid fa-{{ $k['icon'] }}"></i><span><b>{{ $k['label'] }}{{ $k['zusatz'] ? ' · '.$k['zusatz'] : '' }}</b>{{ $k['titel'] }}</span></a>
@endif

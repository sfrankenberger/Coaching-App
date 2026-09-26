@props(['titel' => null, 'icon' => null, 'zahl' => null])
@if ($titel)
    <h2 class="abschnitt">@if ($icon)<i class="fa-solid fa-{{ $icon }}"></i>@endif{{ $titel }}@if ($zahl)<em>{{ $zahl }}</em>@endif</h2>
@endif
<section {{ $attributes->merge(['class' => 'karte']) }}>
    {{ $slot }}
</section>

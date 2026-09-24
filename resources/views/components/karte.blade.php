@props(['titel' => null])
<section {{ $attributes->merge(['class' => 'karte']) }}>
    @if ($titel)
        <h2 class="mb-2">{{ $titel }}</h2>
    @endif
    {{ $slot }}
</section>

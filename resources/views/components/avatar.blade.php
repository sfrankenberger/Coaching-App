{{-- Rundes Profilbild oder der Anfangsbuchstabe --}}
@props(['user', 'size' => 44])
@php $url = $user?->avatarUrl(); @endphp
@if ($url)
    <img src="{{ $url }}" alt="" width="{{ $size }}" height="{{ $size }}" loading="lazy" {{ $attributes->merge(['class' => 'avatar', 'style' => "width:{$size}px;height:{$size}px"]) }}>
@else
    <span {{ $attributes->merge(['class' => 'avatar avatar-buchstabe', 'style' => "width:{$size}px;height:{$size}px;font-size:".round($size * 0.42).'px']) }}>{{ mb_strtoupper(mb_substr($user?->name ?? '?', 0, 1)) }}</span>
@endif

{{-- Kapitel einer Aufzeichnung oder eines Videos: antippen springt, beim Abspielen laeuft die Markierung mit --}}
@props(['text'])
@php $kapitel = \App\Support\Kapitel::liste($text); @endphp
@if (count($kapitel) > 1)
    <ol class="kapitel" data-kapitel>
        @foreach ($kapitel as $k)
            <li><button type="button" data-sprung="{{ $k['sekunden'] }}" data-ohne-scroll><span class="zeit">{{ gmdate($k['sekunden'] >= 3600 ? 'G:i:s' : 'i:s', $k['sekunden']) }}</span><span class="titel">{{ $k['titel'] }}</span></button></li>
        @endforeach
    </ol>
@endif

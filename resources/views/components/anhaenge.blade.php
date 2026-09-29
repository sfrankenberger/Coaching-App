{{-- Alle Anhaenge eines Eintrags (Notiz, Aufgabe, Reflexion, Frage). --}}
@props(['item'])
@php $liste = $item->relationLoaded('anhaenge') ? $item->anhaenge : $item->anhaenge()->with('ziel')->get(); @endphp
@if ($liste->isNotEmpty())
    <div class="anhaenge">
        @foreach ($liste as $an)
            <x-anhang-karte :ziel="$an->ziel" />
        @endforeach
    </div>
@endif

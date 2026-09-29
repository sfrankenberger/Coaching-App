{{-- Etwas anhaengen: Aufgabe, Notiz, Reflexion, Termin, Aufzeichnung, Material, Lektion. Schickt refs[] als "art:nummer". --}}
@props(['refs' => [], 'mehrfach' => true, 'name' => 'refs'])
@php
    $a = app(\App\Support\Anhaenge::class);
    $ich = auth()->user();
    $gewaehlt = collect($refs)->map(fn ($r) => $a->karteRef((string) $r, $ich))->filter()->values();
@endphp
<div class="anhang-wahl" data-anhang-wahl data-suche="{{ route('anhaenge.suche') }}" data-name="{{ $name }}" data-mehrfach="{{ $mehrfach ? '1' : '0' }}" data-auswahl='@json($a->auswahl($ich))'>
    <div class="anhang-zeile">
        <button type="button" class="knopf knopf-leise knopf-klein" data-anhang-auf><i class="fa-solid fa-paperclip"></i>Etwas anhängen</button>
        {{ $slot }}
    </div>
    <div class="anhang-gewaehlt" data-anhang-gewaehlt @if ($gewaehlt->isEmpty()) hidden @endif>
        @foreach ($gewaehlt as $k)
            <span class="anhang-chip" data-ref="{{ $k['ref'] }}"><i class="fa-solid fa-{{ $k['icon'] }}"></i><span>{{ $k['label'] }} · {{ \Illuminate\Support\Str::limit($k['titel'], 40) }}</span><button type="button" data-weg aria-label="Entfernen">&times;</button><input type="hidden" name="{{ $name }}[]" value="{{ $k['ref'] }}"></span>
        @endforeach
    </div>
    <div class="anhang-panel" data-anhang-panel hidden>
        <p class="hinweis m-0">Häng an, worum es geht: eine Aufgabe, eine Notiz, einen Termin, eine Aufzeichnung, Material oder eine Lektion. Hier stehen die letzten je Art, alles andere findest du über die Suche.</p>
        <input type="search" class="feld" placeholder="Aufgabe, Termin, Aufzeichnung, Material oder Lektion suchen" data-anhang-suche aria-label="Suchen">
        <div class="anhang-karten" data-anhang-karten></div>
        <div class="flex justify-end"><button type="button" class="knopf knopf-leise knopf-klein" data-anhang-zu>Fertig</button></div>
    </div>
</div>

{{-- Kleiner Chip eines Projekts (Farbe, Symbol, Name) --}}
@props(['projekt'])
@if ($projekt && App\Support\Funktionen::an('projekte'))
    <a href="{{ route('journal.index', ['projekt' => $projekt->id]) }}" class="projekt-chip no-underline" style="--pc: {{ $projekt->farbe ?: '#B4795F' }}"><i class="fa-solid fa-{{ $projekt->icon ?: 'lightbulb' }}"></i>{{ $projekt->name }}</a>
@endif

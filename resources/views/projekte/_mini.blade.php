{{-- Karte in der Treppe, mit "Verschieben" zum Antippen --}}
<article class="mini" style="--pc: {{ $p->farbe }}">
    <i class="fa-solid fa-{{ $p->icon }}"></i>
    <a href="{{ route('journal.index', ['projekt' => $p->id]) }}" class="n no-underline">{{ $p->name }}</a>
    <details class="relative">
        <summary class="list-none cursor-pointer schieb" aria-label="Verschieben" title="Verschieben"><i class="fa-solid fa-arrows-up-down"></i></summary>
        <form method="post" action="{{ route('projekte.schritt', $p) }}" class="menue" style="right:0;top:32px">
            @csrf
            <span class="hinweis block px-3 pt-1">Wohin?</span>
            @foreach (\App\Models\Projekt::SCHRITTE as $k => [$name, $icon, $farbe])
                <button name="schritt" value="{{ $k }}" @class(['e', 'an' => $k === $hier]) style="--pc: {{ $farbe }}"><i class="fa-solid fa-{{ $icon }}" style="color:var(--pc)"></i>{{ $name }}</button>
            @endforeach
            <button name="schritt" value="" @class(['e', 'an' => $hier === ''])><i class="fa-regular fa-circle"></i>Noch kein Schritt</button>
        </form>
    </details>
</article>

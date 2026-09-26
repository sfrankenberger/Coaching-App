<x-layouts.app title="Werkzeuge">
    <h1 class="mb-1">Werkzeuge</h1>
    <p class="unterzeile m-0 mb-3.5">Methoden aus der Coach-Ausbildung: wofür sie da sind, wann sie passen und wie sie gehen.</p>

    @if (! $darf)
        <x-leer icon="solid:lock" knopf="Mehr dazu" :href="$tuerUrl">Die Werkzeuge gehören zur Coach-Ausbildung. Sobald du dabei bist, findest du sie hier.</x-leer>
    @elseif ($werkzeuge->isEmpty())
        <x-leer icon="solid:hammer">Noch kein Werkzeug da. Das erste kommt bald.</x-leer>
    @else
        @foreach ($werkzeuge as $w)
            <a href="{{ route('werkzeuge.show', $w) }}" class="zeile">
                <span class="ic"><i class="fa-solid fa-hammer"></i></span>
                <span class="tx">
                    <b>{{ $w->title }}@if (! $w->is_published) <span class="chip">Entwurf</span>@endif</b>
                    <span style="white-space:normal">{{ \Illuminate\Support\Str::limit($w->purpose, 140) }}</span>
                    @if ($w->duration || $w->topics->isNotEmpty())<span class="hinweis">{{ implode(' · ', array_filter([$w->duration, $w->topics->pluck('name')->take(3)->implode(' · ')])) }}</span>@endif
                </span>
                <i class="fa-solid fa-chevron-right pf"></i>
            </a>
        @endforeach
    @endif
</x-layouts.app>

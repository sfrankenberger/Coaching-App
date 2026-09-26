@if ($einzeln)
    <p class="m-0 mb-2"><a href="{{ route('nachschlagen.index', ['r' => 'verlauf']) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Alle Suchen</a></p>
    <h2 class="m-0">{{ $einzeln->text }}</h2>
    <p class="hinweis mt-1 mb-3">{{ \App\Support\Zeit::relativ($einzeln->created_at) }}</p>
    @if ($einzeln->answer)<p class="fu-antwort">{{ $einzeln->answer }}</p>@endif
    <div class="fu-liste">
        @forelse ($karten as $k)
            @include('nachschlagen._karte')
        @empty
            <x-leer icon="magnifying-glass">Was du damals gefunden hast, gibt es nicht mehr.</x-leer>
        @endforelse
    </div>
@elseif ($verlauf->isEmpty())
    <x-leer icon="clock">Hier stehen später deine Suchen, mit dem, was du gefunden hast. So findest du auch in zwei Wochen wieder hin.</x-leer>
@else
    @foreach ($verlauf as $h)
        <a class="zeile" href="{{ route('nachschlagen.index', ['r' => 'verlauf', 'v' => $h->id]) }}">
            <span class="ic"><i class="fa-solid fa-{{ $h->kind === 'frage' ? 'comment-dots' : ($h->kind === 'thema' ? 'tag' : 'magnifying-glass') }}"></i></span>
            <span class="tx"><b>{{ \Illuminate\Support\Str::limit($h->text, 70) }}</b><span>{{ \App\Support\Zeit::relativ($h->created_at) }} · {{ count((array) $h->items) }} gefunden</span></span>
            <i class="fa-solid fa-chevron-right pf"></i>
        </a>
    @endforeach
    <form method="post" action="{{ route('nachschlagen.verlauf.leeren') }}" class="mt-3 text-center">
        @csrf @method('delete')
        <button type="submit" class="knopf knopf-leise knopf-klein">Verlauf leeren</button>
    </form>
@endif

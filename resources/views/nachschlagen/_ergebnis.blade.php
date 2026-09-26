@if ($ergebnis !== null)
    @if (! empty($ergebnis['fehler']))
        <p class="fu-antwort">{{ $ergebnis['fehler'] }}</p>
    @elseif ($ergebnis['karten']->isEmpty())
        <p class="fu-antwort">Dazu finde ich nichts. Sag es gern in einem ganzen Satz, dann suche ich anders.</p>
    @else
        @if ($ergebnis['art'] === 'frage')
            <p class="fu-antwort">{{ $ergebnis['antwort'] }}</p>
        @else
            <p class="fu-zahl">{{ $ergebnis['karten']->count() }} zu «{{ $ergebnis['was'] }}»</p>
        @endif
        <div class="fu-liste">
            @foreach ($ergebnis['karten'] as $k)
                @include('nachschlagen._karte')
            @endforeach
        </div>
    @endif
@endif

<div class="dossier-chat">
    @if ($nachrichten->isEmpty())
        <x-leer icon="comment">Noch kein Gespräch mit {{ $person->vorname() }}. Schreib die erste Zeile.</x-leer>
    @else
        <div class="flex flex-col gap-2 mb-3">
            @foreach ($nachrichten as $n)
                @php $m2 = $n; @endphp
                @include('gespraech._nachricht', ['m' => $m2, 'conv' => $conv, 'gelesenBis' => $gelesenBis])
            @endforeach
        </div>
    @endif
    <form method="post" action="{{ route('coachees.nachricht', $m) }}" class="karte">
        @csrf
        <textarea name="body" rows="3" class="feld" placeholder="An {{ $person->vorname() }} schreiben ..." required>{{ old('body') }}</textarea>
        <div class="flex gap-2 flex-wrap items-center">
            <button type="submit" class="knopf knopf-klein"><i class="fa-solid fa-paper-plane"></i>Senden</button>
            <a href="{{ $gespraechUrl }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-comments"></i>Ganzes Gespräch, Sprache, Datei</a>
            @if ($lage['wartet'])
                <form method="post" action="{{ route('coachees.gelesen', $m) }}" class="ml-auto">@csrf<button type="submit" class="knopf knopf-text knopf-klein"><i class="fa-solid fa-check"></i>Als gelesen</button></form>
            @endif
        </div>
    </form>
</div>

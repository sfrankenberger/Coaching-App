@inject('branding', App\Tenancy\Branding::class)
<x-layouts.auth title="Zugriff erlauben">
    <x-karte>
        <h1 style="margin:0 0 6px;font-size:26px">{{ $client->name }} möchte mit der App arbeiten</h1>
        @if ($darf)
            <p class="x m-0 mb-4">Der Assistent darf dann in deinem Namen lesen und schreiben: Personen, Termine, Aufgaben, Nachrichten, Kontakte, Newsletter. Du bist angemeldet als <b>{{ auth()->user()->name }}</b>. Den Zugang kannst du jederzeit im Profil unter «Schlüssel für Verbindungen» löschen.</p>
        @else
            <p class="x m-0 mb-4">Assistenten verbinden dürfen nur {{ $coach }} und das Team. Du bist angemeldet als <b>{{ auth()->user()->name }}</b>.</p>
        @endif
        <form method="post" action="{{ route('oauth.approve') }}" class="eingabe">
            @csrf
            @foreach ($felder as $f)
                <input type="hidden" name="{{ $f }}" value="{{ $werte[$f] ?? '' }}">
            @endforeach
            <div class="eingabe-knoepfe">
                @if ($darf)
                    <button type="submit" name="entscheidung" value="erlauben" class="knopf knopf-breit knopf-gross"><i class="fa-solid fa-check"></i>Erlauben</button>
                @endif
                <button type="submit" name="entscheidung" value="ablehnen" class="knopf knopf-ruhig knopf-breit">Ablehnen</button>
            </div>
        </form>
    </x-karte>
</x-layouts.auth>

<x-layouts.app title="Danke" :schmal="true" body="ohne-leiste">
    <div class="karte" style="text-align:center;padding:32px 22px">
        <i class="fa-regular fa-circle-check" style="font-size:40px;color:var(--c-primary)"></i>
        <h1 class="m-0 mt-3 mb-2">Danke, {{ $v->user->vorname() }}</h1>
        <p class="lesetext m-0">
            @if ($v->zahlungsart === 'kostenlos')
                {{ $offer->title }} ist für dich freigeschaltet.
            @else
                {{ $offer->title }} ist bestellt. Deine Rechnung über {{ $v->betragText() }} kommt per Mail{{ $v->rechnung_link ? ', online bezahlen geht sofort' : '' }}.
            @endif
        </p>
        @if (! ($v->settings['warten_auf_zahlung'] ?? false))
            <p class="lesetext m-0 mt-2">{{ $angemeldet ? 'Dein Zugang ist offen.' : 'In der Mail ist dein Anmeldelink, ein Passwort brauchst du nicht.' }}</p>
        @else
            <p class="lesetext m-0 mt-2">Sobald die Zahlung da ist, öffnet sich dein Zugang, du bekommst Bescheid.</p>
        @endif
        <div class="flex gap-2 justify-center flex-wrap mt-4">
            @if ($v->rechnung_link && $v->zahlungsart === 'rechnung')<a href="{{ $v->rechnung_link }}" target="_blank" rel="noopener" class="knopf"><i class="fa-solid fa-credit-card"></i>Jetzt online bezahlen</a>@endif
            @if ($angemeldet)<a href="{{ route('kurse.index') }}" class="knopf knopf-ruhig">Zu deinen Kursen</a>@endif
        </div>
    </div>
</x-layouts.app>

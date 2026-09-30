<x-layouts.app title="Newsletter" :schmal="true" body="ohne-leiste">
    @php $coach = app(App\Tenancy\Branding::class)->coachName(); @endphp
    @if ($stand === 'postfach')
        <x-leer icon="envelope-open-text">Fast geschafft. Schau in dein Postfach{{ $kontakt->email ? ' ('.$kontakt->email.')' : '' }} und bestätige deine Anmeldung mit einem Klick. Auch im Spam nachsehen.</x-leer>
    @elseif ($stand === 'bestaetigt' || $stand === 'dabei')
        <x-leer icon="circle-check">Du bist dabei{{ $kontakt->vorname() !== 'du' ? ', '.$kontakt->vorname() : '' }}. Danke für dein Vertrauen, bis bald in deinem Postfach.</x-leer>
    @elseif ($stand === 'abgemeldet')
        <x-leer icon="hand-wave">Du bist abgemeldet und bekommst keine Post mehr von {{ $coach }}. Schade, aber alles gut.
            <form method="post" action="{{ route('newsletter.dabei', $kontakt->token) }}" class="mt-3">@csrf<button type="submit" class="knopf knopf-ruhig knopf-klein">Aus Versehen? Wieder anmelden</button></form>
        </x-leer>
    @else
        <x-leer icon="clock">Dieser Link ist abgelaufen. Meld dich einfach nochmals an, dann kommt ein frischer.</x-leer>
    @endif
</x-layouts.app>

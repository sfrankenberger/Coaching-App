<x-layouts.auth title="Kein Zugang">
    <x-karte>
        <h1 class="mb-2">Hier fehlt noch dein Zugang</h1>
        <p class="text-ink-soft">Zu <strong class="text-ink">{{ $email }}</strong> gibt es in diesem Bereich keinen Zugang. Vielleicht hast du dich mit einer anderen Adresse angemeldet?</p>
        <p class="mt-4"><a href="{{ route('anmelden') }}" class="knopf knopf-leise">Nochmals versuchen</a></p>
    </x-karte>
</x-layouts.auth>

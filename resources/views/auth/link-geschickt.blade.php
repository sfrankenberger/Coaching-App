<x-layouts.auth title="Schau in dein Postfach">
    <x-karte>
        <h1 class="mb-2">Schau in dein Postfach</h1>
        <p class="text-ink-soft">Wenn zu <strong class="text-ink">{{ $email }}</strong> ein Zugang besteht, ist der Link unterwegs. Er gilt {{ $minuten }} Minuten und funktioniert einmal.</p>
        <p class="hinweis mt-3">Nichts angekommen? Schau im Spam-Ordner nach oder <a href="{{ route('anmelden') }}">fordere einen neuen Link an</a>.</p>
    </x-karte>
</x-layouts.auth>

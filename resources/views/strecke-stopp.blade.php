<x-layouts.auth title="Alles klar">
    <h1 class="m-0 mb-2">Alles klar</h1>
    <p class="lesetext m-0 mb-4">Zu «{{ $program->title }}» bekommst du keine Erinnerungen mehr. Der Kurs bleibt dir offen, so lange du magst.</p>
    <a href="{{ route('anmelden') }}" class="knopf">Zur Anmeldung</a>
</x-layouts.auth>

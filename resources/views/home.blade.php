<x-layouts.app title="Start">
    @php $person = auth()->user(); $rolle = $person->roleIn(); @endphp
    <x-karte>
        <h1 class="mb-1">Hallo {{ $person->vorname() }}</h1>
        <p class="text-ink-soft">Schön, dass du da bist. Hier entsteht dein Bereich. Kurse, Termine und Nachrichten kommen Schritt für Schritt dazu.</p>
    </x-karte>

    <x-karte titel="Deine Kurse">
        <p class="text-ink-soft mb-3">Kursraum, Wochen, Übungen und dein Fortschritt.</p>
        <a href="{{ route('kurse.index') }}" class="knopf">Zu meinen Kursen</a>
    </x-karte>

    <x-karte titel="Dein Zugang">
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-md">
            <dt class="text-muted">Rolle</dt>
            <dd>{{ $rolle?->label() ?? 'Kein Zugang' }}</dd>
            <dt class="text-muted">E-Mail</dt>
            <dd>{{ $person->email }}</dd>
        </dl>
        <p class="mt-3"><a href="{{ route('profil') }}" class="knopf knopf-leise">Profil bearbeiten</a></p>
    </x-karte>

    @if ($person->canManageCurrentTenant())
        <x-karte titel="Für dich als Coach">
            <p class="text-ink-soft mb-3">Personen, Rollen und bald Kurse pflegst du im Coach-Bereich.</p>
            <a href="/coach" class="knopf">Zum Coach-Bereich</a>
        </x-karte>
    @endif
</x-layouts.app>

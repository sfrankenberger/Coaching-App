<x-layouts.app title="Start">
    @php $person = auth()->user(); $rolle = $person->roleIn(); @endphp
    <x-karte>
        <h1 class="mb-1">Hallo {{ $person->vorname() }}</h1>
        <p class="text-ink-soft">Schön, dass du da bist. Hier entsteht dein Bereich. Kurse, Termine und Nachrichten kommen Schritt für Schritt dazu.</p>
    </x-karte>

    <div class="grid grid-cols-2 gap-2 my-2">
        @foreach ([['kurse.index', 'Kurse', 'Wochen, Übungen, Fortschritt'], ['termine.index', 'Termine', 'Calls und Aufzeichnungen'], ['material.index', 'Material', 'PDFs, Audios, Links'], ['journal.index', 'Journal', 'Aufgaben, Notizen, Reflexion'], ['impulse.index', 'Impulse', 'Beiträge und Podcast'], ['themen.index', 'Themen', 'Finde, was dich gerade beschäftigt'], ['merkliste', 'Merkliste', 'Was du dir gemerkt hast']] as [$r, $t, $x])
            <a href="{{ route($r) }}" class="karte !mt-0 no-underline text-ink hover:border-primary">
                <span class="block text-base font-semibold">{{ $t }}</span>
                <span class="hinweis">{{ $x }}</span>
            </a>
        @endforeach
    </div>

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

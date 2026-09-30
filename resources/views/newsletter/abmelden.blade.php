<x-layouts.app title="Abmelden" :schmal="true" body="ohne-leiste">
    @if ($geschickt ?? false)
        <x-leer icon="envelope-open-text">Wenn diese Adresse bei uns eingetragen ist, hast du gleich Post mit deinem Abmeldelink. Auch im Spam nachsehen.</x-leer>
    @else
        <h1 class="m-0 mb-2">Keine Post mehr?</h1>
        <p class="lesetext m-0 mb-4">Gib deine E-Mail-Adresse ein. Du bekommst einen Link, mit dem du dich mit einem Klick abmeldest.</p>
        <form method="post" action="{{ route('newsletter.abmelden.suchen') }}" class="karte">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            <label class="block mb-3"><span class="feld-label">Deine E-Mail</span><input type="email" name="email" class="feld" required maxlength="190" value="{{ old('email') }}" autocomplete="email"></label>
            @error('email') <p class="fehler mb-2">{{ $message }}</p> @enderror
            <button type="submit" class="knopf knopf-ruhig" style="width:100%">Abmeldelink schicken</button>
        </form>
    @endif
</x-layouts.app>

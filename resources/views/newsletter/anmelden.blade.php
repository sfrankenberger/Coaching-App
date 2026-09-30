<x-layouts.app title="Anmelden" :schmal="true" body="ohne-leiste">
    <h1 class="m-0 mb-2">Post von {{ app(App\Tenancy\Branding::class)->coachName() }}</h1>
    <p class="lesetext m-0 mb-4">Ab und zu ein Impuls, Neues aus dem Programm, Einladungen. Kein Spam, abmelden geht mit einem Klick.</p>
    <form method="post" action="{{ route('newsletter.anmelden.store') }}" class="karte">
        @csrf
        <input type="hidden" name="tag" value="{{ $tag }}">
        @if ($zurueck)<input type="hidden" name="zurueck" value="{{ $zurueck }}">@endif
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
        <label class="block mb-3"><span class="feld-label">Dein Vorname</span><input type="text" name="name" class="feld" maxlength="120" value="{{ old('name') }}" autocomplete="given-name"></label>
        <label class="block mb-3"><span class="feld-label">Deine E-Mail</span><input type="email" name="email" class="feld" required maxlength="190" value="{{ old('email') }}" autocomplete="email"></label>
        @error('email') <p class="fehler mb-2">{{ $message }}</p> @enderror
        <label class="flex items-start gap-2 mb-3 text-md"><input type="checkbox" name="einwilligung" value="1" required class="mt-1"> <span>Ja, schreibt mir. Ich weiss, dass ich mich jederzeit abmelden kann.</span></label>
        @error('einwilligung') <p class="fehler mb-2">{{ $message }}</p> @enderror
        <button type="submit" class="knopf" style="width:100%"><i class="fa-regular fa-envelope"></i>Anmelden</button>
    </form>
</x-layouts.app>

<x-layouts.app title="Profil">
    @php $person = auth()->user(); $einstellungen = $mitgliedschaft?->settings ?? []; @endphp

    <x-karte titel="Über dich">
        <form method="post" action="{{ route('profil.speichern') }}" class="eingabe">
            @csrf
            <div>
                <label for="name" class="feld-label">Name</label>
                <input id="name" name="name" class="feld" required value="{{ old('name', $person->name) }}" autocomplete="name">
                @error('name') <p class="fehler mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone" class="feld-label">Handynummer, freiwillig</label>
                <input id="phone" name="phone" type="tel" class="feld" value="{{ old('phone', $person->phone) }}" placeholder="079 123 45 67" autocomplete="tel">
                <p class="hinweis mt-1">Nur für kurzfristige Absprachen. Du kannst sie jederzeit löschen.</p>
            </div>
            <div class="eingabe-knoepfe">
                <button type="submit" class="knopf">Speichern</button>
            </div>
        </form>
    </x-karte>

    <x-karte titel="Was dich erreicht">
        <p class="hinweis mb-3">Jedes einzeln abschaltbar. Was hier aus ist, kommt weder als Push noch als Mail.</p>
        <form method="post" action="{{ route('profil.benachrichtigungen') }}" class="eingabe">
            @csrf
            @foreach ([
                'termine' => ['Termin-Erinnerungen', 'Am Morgen und eine Stunde vor jedem Call.'],
                'abendmail' => ['Abendmail', 'Abends eine Sammelmail, wenn etwas Neues da ist. Nur ohne Push.'],
                'aufgaben' => ['Aufgaben-Erinnerungen', 'Morgens und abends ein Hinweis auf offene Aufgaben.'],
            ] as $schluessel => [$titel, $text])
                <label class="flex items-start gap-3 py-1">
                    <input type="checkbox" name="{{ $schluessel }}" value="1" class="mt-1 size-5 accent-primary" @checked(data_get($einstellungen, "notifications.$schluessel", true))>
                    <span><b class="block">{{ $titel }}</b><span class="hinweis">{{ $text }}</span></span>
                </label>
            @endforeach
            <div class="eingabe-knoepfe">
                <button type="submit" class="knopf knopf-leise">Speichern</button>
            </div>
        </form>
    </x-karte>

    <x-karte titel="Passwort, freiwillig">
        <p class="hinweis mb-3">Du brauchst kein Passwort, der Link per Mail reicht. Wer trotzdem eines möchte, setzt es hier.</p>
        <form method="post" action="{{ route('profil.passwort') }}" class="eingabe">
            @csrf
            <div>
                <label for="password" class="feld-label">Neues Passwort</label>
                <input id="password" name="password" type="password" class="feld" required autocomplete="new-password" minlength="10">
                @error('password') <p class="fehler mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="feld-label">Nochmals</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="feld" required autocomplete="new-password">
            </div>
            <div class="eingabe-knoepfe">
                <button type="submit" class="knopf knopf-leise">{{ $person->password ? 'Passwort ändern' : 'Passwort setzen' }}</button>
            </div>
        </form>
    </x-karte>
</x-layouts.app>

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

    <x-karte titel="Damit du nichts verpasst">
        <p class="hinweis mb-3">Push-Nachrichten kommen direkt aufs Handy, wenn du diesen Bereich auf den Startbildschirm gelegt hast. Ein paar pro Woche, nicht mehr. Ohne Push bekommst du abends eine Sammelmail, wenn etwas Neues da ist.</p>
        <div class="flex flex-wrap items-center gap-2" data-push data-schluessel="{{ route('push.schluessel') }}" data-abo="{{ route('push.abo') }}">
            <button type="button" class="knopf" data-push-an>Push einschalten</button>
            <button type="button" class="knopf knopf-leise" data-push-aus @if (! $pushGeraete) hidden @endif>Auf diesem Gerät ausschalten</button>
            <span class="hinweis" data-push-status>{{ $pushGeraete ? $pushGeraete.' '.($pushGeraete === 1 ? 'Gerät' : 'Geräte').' angemeldet' : 'Noch kein Gerät angemeldet' }}</span>
        </div>
        @if ($telegram !== false)
            <div class="mt-4 border-t border-line pt-3">
                <b class="block">Telegram</b>
                <p class="hinweis mb-2">Verbinde Telegram, und du bekommst Erinnerungen und Nachrichten auch dort. Antworten kannst du direkt im Telegram-Chat.</p>
                @if ($telegram?->active)
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-md text-success font-semibold">✓ Verbunden{{ $telegram->username ? ' als @'.$telegram->username : '' }}</span>
                        <form method="post" action="{{ route('telegram.trennen') }}">@csrf<button class="knopf knopf-leise" style="min-height:36px;padding:6px 12px">Trennen</button></form>
                    </div>
                @elseif ($telegram?->code)
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="https://t.me/{{ $telegramBot }}?start={{ $telegram->code }}" target="_blank" rel="noopener" class="knopf">Bot öffnen und «Start» tippen</a>
                        <span class="hinweis">Code: {{ $telegram->code }}</span>
                    </div>
                @else
                    <form method="post" action="{{ route('telegram.verbinden') }}">@csrf<button class="knopf knopf-leise">Telegram verbinden</button></form>
                @endif
            </div>
        @endif
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

    @if ($kalenderUrl)
        <x-karte titel="Termine im eigenen Kalender">
            <p class="hinweis mb-3">Abonniere deine Termine in Apple Kalender, Google Kalender oder Outlook. Änderungen kommen von selbst nach. Der Link ist nur für dich, gib ihn nicht weiter.</p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ preg_replace('~^https?://~', 'webcal://', $kalenderUrl) }}" class="knopf">Kalender abonnieren</a>
                <button type="button" class="knopf knopf-leise" data-kopieren="{{ $kalenderUrl }}">Link kopieren</button>
            </div>
        </x-karte>
    @endif

    <x-karte titel="Passkey: anmelden mit Fingerabdruck oder Gesicht" data-passkey-box>
        <p class="hinweis mb-3">Ein Passkey ersetzt Link und Passwort: einmal auf diesem Gerät anlegen, danach genügt Fingerabdruck, Gesicht oder Geräte-Code. Gilt nur für diese Adresse.</p>
        @if ($passkeys->isNotEmpty())
            <ul class="divide-y divide-line mb-3">
                @foreach ($passkeys as $pk)
                    <li class="flex items-center gap-3 py-2">
                        <span class="min-w-0 flex-1">
                            <span class="block text-base">{{ $pk->alias ?: 'Passkey' }}</span>
                            <span class="hinweis">angelegt {{ $pk->created_at->translatedFormat('j. F Y') }}</span>
                        </span>
                        <form method="post" action="{{ route('passkeys.loeschen', $pk->id) }}" onsubmit="return confirm('Diesen Passkey entfernen?')">@csrf @method('DELETE')<button class="knopf knopf-leise" style="min-height:36px;padding:6px 12px">Entfernen</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" name="alias" class="feld" style="max-width:220px" placeholder="Name, z. B. iPhone" maxlength="60">
            <button type="button" class="knopf" data-passkey="anlegen" data-optionen="{{ route('passkeys.anlegen.optionen') }}" data-speichern="{{ route('passkeys.anlegen') }}">Passkey anlegen</button>
            <span class="hinweis" data-passkey-status></span>
        </div>
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

    <x-karte titel="Hilfe">
        <p class="hinweis mb-3">Die Einführung vom Anfang kannst du jederzeit wieder anschauen.</p>
        <a href="{{ route('willkommen') }}" class="knopf knopf-leise">Einführung nochmals ansehen</a>
    </x-karte>

    @push('scripts')<script src="{{ asset('js/passkeys.js') }}?v={{ filemtime(public_path('js/passkeys.js')) }}" defer></script>@endpush
</x-layouts.app>

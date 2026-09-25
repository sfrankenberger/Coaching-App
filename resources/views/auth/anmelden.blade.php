@inject('branding', App\Tenancy\Branding::class)
<x-layouts.auth title="Anmelden">
    <x-karte>
        <h1 style="margin:0 0 6px;font-size:26px">Schön, dass du da bist</h1>
        <p class="x" style="margin:0 0 16px">Gib deine E-Mail-Adresse ein. Du bekommst einen Link, mit dem du direkt drin bist. Kein Passwort nötig.</p>

        <form method="post" action="{{ route('anmelden.link') }}" class="eingabe">
            @csrf
            <input type="hidden" name="weiter" value="{{ $weiter ?? '' }}">
            <div>
                <label for="email" class="feld-label">E-Mail-Adresse</label>
                <input id="email" name="email" type="email" class="feld" required autocomplete="email" inputmode="email" autofocus value="{{ old('email') }}">
                @error('email') <p class="fehler mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="eingabe-knoepfe">
                <button type="submit" class="knopf knopf-breit knopf-gross"><i class="fa-solid fa-envelope"></i>Link schicken</button>
            </div>
        </form>

        <div class="mt-3" data-passkey-box>
            <button type="button" class="knopf knopf-ruhig knopf-breit" data-passkey="anmelden" data-optionen="{{ route('passkeys.anmelden.optionen') }}" data-anmelden="{{ route('passkeys.anmelden') }}" data-weiter="{{ $weiter ?? '' }}"><i class="fa-solid fa-fingerprint"></i>Mit Passkey anmelden</button>
            <p class="hinweis mt-1 text-center" data-passkey-status></p>
        </div>

        @if ($dienste !== [])
            <div class="relative my-5 text-center">
                <span class="relative z-10 bg-card px-3 hinweis">oder</span>
                <span class="absolute inset-x-0 top-1/2 border-t border-line"></span>
            </div>
            <div class="flex flex-col gap-2">
                @foreach ($dienste as $dienst => $label)
                    <a href="{{ route('anmelden.dienst', ['dienst' => $dienst, 'weiter' => $weiter ?? null]) }}" @class(['knopf knopf-breit', 'knopf-ruhig' => $dienst !== 'apple']) @if ($dienst === 'apple') style="background:#000;border-color:#000;color:#fff" @endif><i class="fa-brands fa-{{ $dienst }}"></i>Mit {{ $label }} anmelden</a>
                @endforeach
            </div>
        @endif

        <details class="mt-5">
            <summary class="hinweis cursor-pointer">Lieber mit Passwort anmelden</summary>
            <form method="post" action="{{ route('anmelden.passwort') }}" class="eingabe mt-3">
                @csrf
                <input type="hidden" name="weiter" value="{{ $weiter ?? '' }}">
                <div>
                    <label for="pw-email" class="feld-label">E-Mail-Adresse</label>
                    <input id="pw-email" name="email" type="email" class="feld" required autocomplete="email" value="{{ old('email') }}">
                </div>
                <div>
                    <label for="pw" class="feld-label">Passwort</label>
                    <input id="pw" name="password" type="password" class="feld" required autocomplete="current-password">
                    @error('password') <p class="fehler mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="eingabe-knoepfe">
                    <button type="submit" class="knopf knopf-leise knopf-breit">Anmelden</button>
                </div>
            </form>
        </details>
    </x-karte>

    @push('scripts')<script src="{{ asset('js/passkeys.js') }}?v={{ filemtime(public_path('js/passkeys.js')) }}" defer></script>@endpush
</x-layouts.auth>

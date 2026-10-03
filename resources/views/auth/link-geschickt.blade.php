<x-layouts.auth title="Schau in dein Postfach">
    <x-karte>
        <h1 class="mb-2">Schau in dein Postfach</h1>
        <p class="text-ink-soft">Wenn zu <strong class="text-ink">{{ $email }}</strong> ein Zugang besteht, ist die Mail unterwegs. Darin steht ein Code und ein Link, beides gilt {{ $minuten }} Minuten und funktioniert einmal.</p>
        <form method="post" action="{{ route('anmelden.code') }}" class="eingabe mt-4">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <input type="hidden" name="weiter" value="{{ $weiter ?? '' }}">
            <div>
                <label for="code" class="feld-label">Code aus der Mail</label>
                <input id="code" name="code" type="text" class="feld" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="000 000" autofocus style="font-size:22px;letter-spacing:4px;text-align:center">
            </div>
            <div class="eingabe-knoepfe">
                <button type="submit" class="knopf knopf-breit knopf-gross"><i class="fa-solid fa-key"></i>Anmelden</button>
            </div>
        </form>
        <p class="hinweis mt-3">Am Computer kannst du auch einfach den Link in der Mail antippen. Nichts angekommen? Schau im Spam-Ordner nach oder <a href="{{ route('anmelden') }}">fordere einen neuen Code an</a>.</p>
    </x-karte>
</x-layouts.auth>

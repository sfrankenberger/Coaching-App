<x-layouts.app title="Hilfe" :schmal="true">
    <h1 class="mb-1">Hilfe</h1>
    <p class="unterzeile m-0 mb-3">Wenn etwas klemmt, bist du hier richtig.</p>
    <x-karte titel="Hilfe" icon="life-ring">
        <p class="x m-0 mb-3">Klemmt etwas? Schreib kurz, wo und was passiert. Seite, Gerät und Browser schicken wir automatisch mit, dann geht es schneller.</p>
        <form method="post" action="{{ route('profil.hilfe') }}" class="eingabe" data-hilfe>
            @csrf
            <input type="hidden" name="seite" value="{{ url()->previous() }}">
            <input type="hidden" name="geraet" value="">
            <div>
                <label for="hilfe-wo" class="feld-label">Wo klemmt es?</label>
                <input id="hilfe-wo" name="wo" class="feld" maxlength="200" placeholder="z. B. beim Abspielen des Videos in Woche 2">
            </div>
            <div>
                <label for="hilfe-was" class="feld-label">Was passiert?</label>
                <textarea id="hilfe-was" name="was" class="feld" rows="3" required maxlength="3000" placeholder="Was hast du gemacht, und was ist dann passiert?"></textarea>
            </div>
            <div class="eingabe-knoepfe" style="justify-content:space-between">
                <a href="{{ route('willkommen') }}" class="knopf knopf-anstoss"><i class="fa-solid fa-circle-info"></i>Einführung ansehen</a>
                <button type="submit" class="knopf knopf-dunkel"><i class="fa-solid fa-paper-plane"></i>Technik melden</button>
            </div>
        </form>
        @php $wa = data_get(app(\App\Tenancy\CurrentTenant::class)->get()?->settings, 'support.whatsapp'); $waName = data_get(app(\App\Tenancy\CurrentTenant::class)->get()?->settings, 'support.name'); @endphp
        @if ($wa)
            <p class="m-0 mt-3"><a href="https://wa.me/{{ preg_replace('~\D+~', '', $wa) }}?text={{ rawurlencode('Hallo'.($waName ? ' '.$waName : '').', ich habe ein technisches Problem in der App: ') }}" target="_blank" rel="noopener" class="knopf knopf-ruhig"><i class="fa-brands fa-whatsapp"></i>Live-Chat{{ $waName ? ' mit '.$waName : '' }} auf WhatsApp</a></p>
        @endif
    </x-karte>

</x-layouts.app>

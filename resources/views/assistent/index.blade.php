<x-layouts.app title="Assistent">
    <h1 class="mb-1">Assistent</h1>
    <p class="unterzeile m-0 mb-3.5">Frag nach deinem Betrieb, merk dir Wichtiges, und verbinde Claude oder ChatGPT mit der App.</p>

    <section class="karte" data-auskunft data-url="{{ route('coachees.frage') }}">
        <h3 class="m-0" style="font-size:var(--fs-lg)">Frag mich etwas</h3>
        <p class="hinweis m-0 mb-2">Buchungen, Termine, Menschen, wo du was findest, und was du dir gemerkt hast.</p>
        <form class="suche m-0" data-auskunft-form>
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <input type="search" name="frage" placeholder="Frag mich: was hat Nicole gebucht, wo trage ich Zeiten ein" aria-label="Frage" autocomplete="off">
            <button type="submit" class="knopf knopf-klein" data-auskunft-los>Fragen</button>
        </form>
        <p class="hinweis mt-2 mb-0 flex flex-wrap gap-1.5 items-center">Zum Beispiel:
            @foreach ($beispiele as $b)<button type="button" class="pille" style="min-height:30px;padding:4px 11px;font-size:var(--fs-xs)" data-auskunft-beispiel>{{ $b }}</button>@endforeach
        </p>
        <div class="mt-3" data-auskunft-antwort aria-live="polite"></div>
        @unless ($ki)<p class="hinweis mt-2 mb-0">Ohne KI-Schlüssel antworte ich nur mit Fakten aus der App (Menschen, Orte, Inhalte). Den Schlüssel trägst du unter Einstellungen ein.</p>@endunless
    </section>

    {{-- Second Brain --}}
    <h2 class="abschnitt"><i class="fa-solid fa-brain"></i>Mein Wissen<em>{{ $wissenAnzahl }}</em></h2>
    <p class="hinweis mb-2">Dein zweites Gedächtnis: Regeln, Preise, Abläufe, Gedanken. Der Assistent kennt das, und Claude oder ChatGPT auch, wenn sie verbunden sind.</p>
    <form method="post" action="{{ route('assistent.merken') }}" class="karte">
        @csrf
        <textarea name="body" rows="3" class="feld" placeholder="Zum Beispiel: Erstgespräche dauern 30 Minuten und sind gratis. Oder: Nicole mag keine Mails am Wochenende." required>{{ old('body') }}</textarea>
        <div class="grid gap-2" style="grid-template-columns:1fr 1fr">
            <label class="feld-label">Titel (freiwillig)<input type="text" name="title" class="feld" maxlength="200"></label>
            <label class="feld-label">Schlagworte, mit Komma<input type="text" name="tags" class="feld" placeholder="preise, ablauf"></label>
        </div>
        <button type="submit" class="knopf knopf-klein"><i class="fa-solid fa-bookmark"></i>Merken</button>
    </form>
    <form method="get" class="suche m-0 mb-2">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="wissen" value="{{ $q }}" placeholder="Im Wissen suchen" aria-label="Im Wissen suchen">
    </form>
    @forelse ($wissen as $w)
        <div class="karte">
            <span class="eyebrow">{{ $w->title }} · {{ \App\Support\Zeit::wannKurz($w->created_at) }}{{ $w->user ? ' · '.$w->user->vorname() : '' }}{{ $w->source !== 'app' ? ' · via '.$w->source : '' }}</span>
            <p class="lesetext text-md mt-1 mb-0 whitespace-pre-line">{{ $w->body }}</p>
            @if ($w->tags)<p class="pillen mt-2 mb-0">@foreach ($w->tags as $t)<span class="pille" style="min-height:26px;padding:2px 10px;font-size:var(--fs-xs)">{{ $t }}</span>@endforeach</p>@endif
            <form method="post" action="{{ route('assistent.vergessen', $w) }}" onsubmit="return confirm('Diesen Eintrag vergessen?')" class="mt-1">@csrf @method('DELETE')<button type="submit" class="knopf knopf-text knopf-klein">Vergessen</button></form>
        </div>
    @empty
        <p class="hinweis">{{ $q !== '' ? 'Nichts gefunden.' : 'Noch nichts gemerkt.' }}</p>
    @endforelse

    {{-- MCP --}}
    <h2 class="abschnitt"><i class="fa-solid fa-plug"></i>Claude oder ChatGPT verbinden</h2>
    <div class="karte">
        <p class="text-md m-0 mb-2">Die App ist ein MCP-Server. Verbunden kann Claude (oder ein anderer Assistent) Menschen anlegen, Zugänge geben, Nachrichten schicken, Termine eintragen, dein Wissen lesen und ergänzen. Alles läuft mit deinem Konto und bleibt in diesem Betrieb.</p>
        <p class="text-md m-0 mb-1"><b>Adresse:</b> <code>{{ $mcpUrl }}</code></p>
        <p class="text-md m-0 mb-2"><b>Schlüssel:</b> unter <a href="{{ route('profil') }}#api">Profil, Schlüssel für Verbindungen</a>. In Claude als "Bearer"-Token eintragen.</p>
        <details><summary class="hinweis cursor-pointer">So richtest du es ein</summary>
            <ol class="text-md mt-2 mb-0 pl-4" style="line-height:1.6">
                <li>Schlüssel im Profil erzeugen und kopieren.</li>
                <li>Claude: Einstellungen, Connectors, "Custom connector" hinzufügen. Adresse oben eintragen, den Schlüssel als Bearer-Token.</li>
                <li>ChatGPT: Einstellungen, Connectors, "MCP-Server" (Developer Mode). Gleiche Adresse, gleicher Schlüssel.</li>
                <li>Dann einfach fragen: "Leg Anna Muster an und gib ihr den Jahreskurs" oder "Wer wartet auf meine Antwort?"</li>
            </ol>
        </details>
    </div>
</x-layouts.app>

<x-layouts.app title="Assistent">
    <h1 class="mb-1">Assistent</h1>
    <p class="unterzeile m-0 mb-3.5">Frag nach deinem Betrieb, merk dir Wichtiges, und verbinde Claude oder ChatGPT mit der App.</p>

    {{-- Claude in der App: Gespraech mit Werkzeugen, Rueckfrage vor jedem Schreiben --}}
    <div id="chat"></div>
    <section class="karte chat-assistent">
        <div class="flex items-center justify-between gap-2">
            <h3 class="m-0" style="font-size:var(--fs-lg)"><i class="fa-solid fa-wand-magic-sparkles"></i> Frag mich oder gib mir etwas zu tun</h3>
            @if ($chat['protokoll'])<form method="post" action="{{ route('assistent.chat.neu') }}">@csrf<button type="submit" class="knopf knopf-text knopf-klein">Neues Gespräch</button></form>@endif
        </div>
        <p class="hinweis m-0 mb-2">Ich kenne deinen Betrieb und kann handeln: Personen, Zugänge, Nachrichten, Termine, Kontakte, Newsletter, Impulse, Rundnachrichten. Bevor ich etwas ändere, frage ich nach.</p>
        @if ($chat['protokoll'])
            <div class="flex flex-col gap-2 mb-3" data-chat-protokoll>
                @foreach ($chat['protokoll'] as $z)
                    @if ($z['rolle'] === 'du')
                        <div class="flex justify-end"><div class="blase blase-meine"><div class="lesetext whitespace-pre-line text-md">{{ $z['text'] }}</div></div></div>
                    @elseif ($z['rolle'] === 'ki')
                        <div class="flex justify-start"><div class="blase"><div class="lesetext whitespace-pre-line text-md">{{ $z['text'] }}</div></div></div>
                    @else
                        <details class="hinweis" style="margin-left:6px">
                            <summary class="cursor-pointer"><i class="fa-solid fa-{{ $z['fehler'] ? 'triangle-exclamation' : 'check' }}"></i> {{ str_replace('_', ' ', $z['name']) }}{{ $z['fehler'] ? ': '.$z['ergebnis'] : '' }}</summary>
                            <pre class="text-xs whitespace-pre-wrap m-0 mt-1" style="font-family:var(--font-body)">{{ json_encode($z['args'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
{{ $z['ergebnis'] }}</pre>
                        </details>
                    @endif
                @endforeach
            </div>
        @endif
        @if ($chat['offen'])
            <div class="karte" style="border-color:var(--c-primary-line);background:var(--c-primary-tint)">
                <span class="eyebrow"><i class="fa-solid fa-hand"></i> Soll ich das machen?</span>
                @foreach ($chat['offen']['werkzeuge'] as $w)
                    <p class="text-md m-0 mt-1"><b>{{ $werkzeuge->get($w['name'])?->beschreibung() ? str_replace('_', ' ', $w['name']) : $w['name'] }}</b></p>
                    <pre class="text-xs whitespace-pre-wrap m-0" style="font-family:var(--font-body)">{{ collect($w['input'])->map(fn ($v, $k) => $k.': '.(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v))->join("
") }}</pre>
                @endforeach
                <form method="post" action="{{ route('assistent.chat.entscheiden') }}" class="flex gap-2 mt-2">
                    @csrf
                    <button type="submit" name="ja" value="1" class="knopf knopf-klein"><i class="fa-solid fa-check"></i>Ja, ausführen</button>
                    <button type="submit" name="ja" value="0" class="knopf knopf-ruhig knopf-klein">Abbrechen</button>
                </form>
            </div>
        @else
            <form method="post" action="{{ route('assistent.chat') }}" class="flex gap-2 items-end">
                @csrf
                <textarea name="text" rows="2" class="feld flex-1" required maxlength="4000" placeholder="{{ $chat['protokoll'] ? 'Und weiter ...' : 'Zum Beispiel: Leg einen Newsletter aus dem letzten Impuls an, an alle mit Tag newsletter' }}" onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){this.form.requestSubmit()}"></textarea>
                <button type="submit" class="knopf" @unless ($ki) disabled title="Ohne KI-Schlüssel" @endunless><i class="fa-solid fa-paper-plane"></i></button>
            </form>
            @unless ($chat['protokoll'])
                <p class="hinweis mt-2 mb-0 flex flex-wrap gap-1.5 items-center">Zum Beispiel:
                    @foreach ($beispiele as $b)<button type="button" class="pille" style="min-height:30px;padding:4px 11px;font-size:var(--fs-xs)" data-dialog-beispiel>{{ $b }}</button>@endforeach
                </p>
            @endunless
            @unless ($ki)<p class="hinweis mt-2 mb-0">Dafür braucht es den KI-Schlüssel, den trägst du unter Einstellungen ein.</p>@endunless
        @endif
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

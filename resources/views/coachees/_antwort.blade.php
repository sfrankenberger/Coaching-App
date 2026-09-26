@if ($msg)
    <p class="fu-antwort">{{ $msg }}</p>
@else
    @if ($a['text'])<p class="fu-antwort" style="white-space:pre-line">{{ $a['text'] }}</p>@endif
    @if ($a['fehler'])<p class="hinweis">{{ $a['fehler'] }}</p>@endif
    @if (! $a['text'] && ! $a['menschen'] && ! $a['orte'] && ! $a['inhalte'] && ! $a['mehrdeutig'])
        <p class="fu-antwort">Dazu finde ich nichts. Nenn mir einen Namen oder sag, wonach du suchst.</p>
    @endif
    @foreach ($a['mehrdeutig'] as $wort => $namen)
        <p class="hinweis"><b>{{ $wort }}</b>: wen meinst du? {{ implode(', ', $namen) }}</p>
    @endforeach
    @foreach ($a['menschen'] as $p)
        <div class="karte">
            <b class="t">{{ $p['name'] }}</b><span class="hinweis block">{{ $p['mail'] }}{{ $p['telefon'] ? ' · '.$p['telefon'] : '' }} · {{ $p['rolle'] }}</span>
            <ul class="m-0 mt-2 pl-4 text-md" style="line-height:1.6">
                <li><b>Programme:</b> {{ $p['kurse'] ? implode(', ', $p['kurse']) : 'keine' }}</li>
                @if ($p['zugaenge'])<li><b>Zugänge:</b> {{ implode(', ', $p['zugaenge']) }}</li>@endif
                @if (! empty($p['sitzungen']))<li><b>Sitzungen:</b> {{ $p['sitzungen'] }}</li>@endif
                <li><b>Nächster 1:1-Termin:</b> {{ $p['naechster_1zu1_termin'] }}</li>
                <li><b>Letzter 1:1-Termin:</b> {{ $p['letzter_1zu1_termin'] }}</li>
                <li><b>Aufgaben:</b> {{ $p['aufgaben'] }}</li>
                <li><b>Gespräch:</b> {{ $p['gespraech'] }}</li>
                @if ($p['buchungen'])<li><b>Buchungen:</b> {{ implode(' · ', $p['buchungen']) }}</li>@endif
                <li><b>Zuletzt da:</b> {{ $p['zuletzt_da'] }}{{ $p['lage'] ? ' · '.$p['lage'] : '' }}</li>
            </ul>
            <div class="flex gap-2 mt-2">
                <a href="{{ $p['dossier'] }}" class="knopf knopf-leise knopf-klein">Dossier öffnen</a>
                @if ($p['gespraech_url'])<a href="{{ $p['gespraech_url'] }}" class="knopf knopf-leise knopf-klein">Gespräch</a>@endif
            </div>
        </div>
    @endforeach
    @if ($a['inhalte'])
        <div class="karte"><b class="t">Inhalte</b>
            <ul class="m-0 mt-1 pl-4 text-md" style="line-height:1.6">@foreach ($a['inhalte'] as $i)<li><a href="{{ $i['url'] }}">{{ $i['titel'] }}</a> <span class="hinweis">{{ $i['art'] }}</span></li>@endforeach</ul>
        </div>
    @endif
    @if ($a['orte'])
        <div class="karte"><b class="t">Wo du es findest</b>
            <ul class="m-0 mt-1 pl-4 text-md" style="line-height:1.6">@foreach ($a['orte'] as $o)<li><a href="{{ $o['u'] }}">{{ $o['t'] }}</a></li>@endforeach</ul>
        </div>
    @endif
@endif

<x-filament-panels::page>
    @php $z = $this->zahlen(); $geld = fn ($s) => \App\Shop\Zahlen::geld($s); @endphp
    <p class="text-sm text-gray-500" style="margin:0 0 12px">Grundlage sind die Verkäufe über die App (Kasse, Dossier, Stripe). Stornierte zählen nicht. Was vor der App in bexio lief, steht dort.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
        @foreach ([['Umsatz '.now()->format('Y'), $geld($z['jahr']), null], ['Umsatz '.now()->translatedFormat('F'), $geld($z['monat']), null], ['Offen', $geld($z['offen']), $z['offen_anzahl'].' '.($z['offen_anzahl'] === 1 ? 'Rechnung' : 'Rechnungen').($z['ueberfaellig'] ? ', '.$z['ueberfaellig'].' überfällig' : '')], ['Gesamt', $geld($z['gesamt']), $z['verkaeufe'].' Verkäufe']] as [$titel, $wert, $unter])
            <div class="rounded border p-4">
                <div class="text-sm text-gray-500">{{ $titel }}</div>
                <div style="font-size:22px;font-weight:600;margin-top:4px">{{ $wert }}</div>
                @if ($unter)<div class="text-sm text-gray-500" style="margin-top:2px">{{ $unter }}</div>@endif
            </div>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
        <div class="rounded border p-4">
            <h3 style="margin:0 0 8px;font-weight:600">Letzte 12 Monate</h3>
            <table style="width:100%;font-size:14px">
                @foreach ($z['monate'] as $m)
                    <tr><td style="padding:3px 0">{{ $m['label'] }}</td><td style="text-align:right;color:#6b7280">{{ $m['anzahl'] ?: '' }}</td><td style="text-align:right;padding:3px 0">{{ $m['anzahl'] ? $geld($m['summen']) : '–' }}</td></tr>
                @endforeach
            </table>
        </div>
        <div class="rounded border p-4">
            <h3 style="margin:0 0 8px;font-weight:600">Jahre</h3>
            @if ($z['jahre']->isEmpty())<p class="text-sm text-gray-500">Noch nichts.</p>@endif
            <table style="width:100%;font-size:14px">
                @foreach ($z['jahre'] as $jahr => $summen)
                    <tr><td style="padding:3px 0">{{ $jahr }}</td><td style="text-align:right;padding:3px 0">{{ $geld($summen) }}</td></tr>
                @endforeach
            </table>
            <h3 style="margin:16px 0 8px;font-weight:600">Angebote</h3>
            @if ($z['angebote']->isEmpty())<p class="text-sm text-gray-500">Noch nichts.</p>@endif
            <table style="width:100%;font-size:14px">
                @foreach ($z['angebote'] as $a)
                    <tr><td style="padding:3px 0">{{ $a['titel'] }}</td><td style="text-align:right;color:#6b7280">{{ $a['anzahl'] }}×</td><td style="text-align:right;padding:3px 0">{{ $geld($a['summen']) }}</td></tr>
                @endforeach
            </table>
        </div>
        <div class="rounded border p-4">
            <h3 style="margin:0 0 8px;font-weight:600">Beste Kundinnen</h3>
            @if ($z['beste']->isEmpty())<p class="text-sm text-gray-500">Noch nichts.</p>@endif
            <table style="width:100%;font-size:14px">
                @foreach ($z['beste'] as $b)
                    @php $ms = \App\Models\Membership::where('user_id', $b['user']->id)->first(); @endphp
                    <tr>
                        <td style="padding:3px 6px 3px 0;color:#6b7280">{{ $b['rang'] }}.</td>
                        <td style="padding:3px 0">@if ($ms)<a href="{{ route('coachees.show', $ms) }}" style="text-decoration:underline">{{ $b['user']->name }}</a>@else{{ $b['user']->name }}@endif <span class="text-gray-500">· {{ $b['anzahl'] }}×</span></td>
                        <td style="text-align:right;padding:3px 0">{{ $geld($b['summen']) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</x-filament-panels::page>

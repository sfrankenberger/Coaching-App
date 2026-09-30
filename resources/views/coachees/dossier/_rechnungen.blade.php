@if ($zahlenSichtbar)
    @php $z = $zahlen; $geld = fn ($s) => \App\Shop\Zahlen::geld($s); @endphp
    <div class="karte" style="margin-bottom:12px">
        <div class="flex flex-wrap gap-x-5 gap-y-2" style="font-size:14px">
            <span><span class="hinweis">Umsatz</span><br><b>{{ $geld($z['summen']) }}</b>{{ $z['anzahl'] ? ' · '.$z['anzahl'].' '.($z['anzahl'] === 1 ? 'Rechnung' : 'Rechnungen') : '' }}</span>
            <span><span class="hinweis">Offen</span><br><b>{{ $z['offen_anzahl'] ? $geld($z['offen']) : 'nichts' }}</b></span>
            @if ($z['rang'])<span><span class="hinweis">Rang</span><br><b>{{ $z['rang'] }} von {{ $z['von'] }}</b>{{ $z['anteil'] !== null ? ' · '.$z['anteil'].' % vom Ganzen' : '' }}</span>@endif
            @if ($z['letzte'])<span><span class="hinweis">Letzte Rechnung</span><br><b>{{ \App\Support\Zeit::datum($z['letzte']->created_at) }}</b> · {{ $z['letzte']->title }}</span>@endif
            @if ($z['dabei_seit'])<span><span class="hinweis">Dabei seit</span><br><b>{{ \App\Support\Zeit::datum($z['dabei_seit']) }}</b></span>@endif
        </div>
        @if ($z['wofuer'])<p class="hinweis" style="margin:8px 0 0">Wofür bezahlt: {{ implode(', ', $z['wofuer']) }}</p>@endif
        <p style="margin:8px 0 0;font-size:14px"><i class="fa-regular fa-lightbulb" style="color:var(--c-ghost)"></i> {{ $z['hinweis'] }}</p>
    </div>
@endif
@if (! $buchhaltung || ! $buchhaltung->verbunden())
    <x-leer icon="file-invoice" knopf="Buchhaltung einrichten" href="/coach/buchhaltung">Noch keine Buchhaltung verbunden. Sobald bexio verbunden ist, stehen hier alle Rechnungen von {{ $person->vorname() }}.</x-leer>
@elseif ($rechnungenFehler)
    <p class="hinweis"><i class="fa-solid fa-triangle-exclamation"></i> Die Rechnungen lassen sich gerade nicht laden: {{ $rechnungenFehler }}</p>
@elseif ($rechnungen->isEmpty())
    <x-leer icon="file-invoice">Keine Rechnungen für {{ $person->vorname() }} in {{ $buchhaltung->name() }}. Gesucht wird nach der Mail-Adresse {{ $person->email }}.</x-leer>
@else
    @php $offen = $buchhaltung->offen($person); @endphp
    <div class="flex items-center justify-between gap-2 mb-2">
        <span class="hinweis">{{ $rechnungen->count() }} {{ $rechnungen->count() === 1 ? 'Rechnung' : 'Rechnungen' }} in {{ $buchhaltung->name() }}@if ($offen) · offen: @foreach ($offen as $w => $s){{ number_format($s, 2, '.', "'") }} {{ $w }}@if (! $loop->last), @endif @endforeach @endif</span>
        <a href="{{ route('coachees.show', [$m, 'r' => 'rechnungen', 'frisch' => 1]) }}" class="knopf knopf-text knopf-klein"><i class="fa-solid fa-rotate"></i>Neu laden</a>
    </div>
    <div class="karte">
        <x-rechnungen :rechnungen="$rechnungen" pdf-route="coachees.rechnung" :pdf-params="['membership' => $m->id]" />
    </div>
@endif

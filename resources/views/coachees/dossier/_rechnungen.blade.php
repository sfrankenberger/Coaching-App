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

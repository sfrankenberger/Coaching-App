{{-- Rechnungen einer Person aus der Buchhaltung: Zeile je Rechnung mit Status, PDF und "online bezahlen" --}}
@props(['rechnungen', 'pdfRoute', 'pdfParams' => []])
@foreach ($rechnungen as $r)
    @php $offen = in_array($r['status'], ['offen', 'teilweise', 'gemahnt'], true); @endphp
    <div @class(['zeile', 'fertig' => ! $offen]) style="align-items:flex-start">
        <span class="ic"><i class="fa-regular fa-file-lines"></i></span>
        <span class="tx">
            <b>{{ $r['titel'] ?: 'Rechnung '.$r['nr'] }}</b>
            <span>
                @if ($r['nr']){{ $r['nr'] }} · @endif
                {{ number_format($r['betrag'], 2, '.', "'") }} {{ $r['waehrung'] }}
                @if ($r['datum']) · {{ \Carbon\Carbon::parse($r['datum'])->translatedFormat('j. M Y') }}@endif
                · <span @class(['chip', 'chip-warn' => $r['status'] === 'gemahnt', 'chip-ok' => $r['status'] === 'bezahlt'])>{{ \App\Shop\Buchhaltung::statusText($r['status']) }}</span>
                @if ($offen && $r['faellig']) · fällig {{ \Carbon\Carbon::parse($r['faellig'])->translatedFormat('j. M Y') }}@endif
            </span>
            <span class="flex flex-wrap gap-2 mt-2">
                <a href="{{ route($pdfRoute, array_merge($pdfParams, ['rechnung' => $r['id']])) }}" target="_blank" rel="noopener" class="knopf knopf-ruhig knopf-klein"><i class="fa-regular fa-file-pdf"></i>PDF</a>
                @if ($offen && $r['link'])<a href="{{ $r['link'] }}" target="_blank" rel="noopener" class="knopf knopf-klein"><i class="fa-solid fa-credit-card"></i>Online bezahlen</a>@endif
            </span>
        </span>
    </div>
@endforeach

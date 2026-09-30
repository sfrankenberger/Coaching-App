@php $zeilen = $eintrag->aenderungen(); @endphp
<div style="font-size:.875rem">
    @if (empty($zeilen))
        <p style="color:rgb(107 114 128)">Keine Feldänderungen festgehalten.</p>
    @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="color:rgb(107 114 128);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">
                    <th style="text-align:left;padding:.375rem .5rem">Feld</th>
                    <th style="text-align:left;padding:.375rem .5rem">Vorher</th>
                    <th style="text-align:left;padding:.375rem .5rem">Nachher</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($zeilen as $z)
                    <tr style="border-top:1px solid rgb(229 231 235);vertical-align:top">
                        <td style="padding:.375rem .5rem;font-weight:600;white-space:nowrap">{{ $z['feld'] }}</td>
                        @if ($z['maskiert'])
                            <td colspan="2" style="padding:.375rem .5rem;color:rgb(107 114 128);font-style:italic">geändert (Inhalt wird nicht protokolliert)</td>
                        @else
                            <td style="padding:.375rem .5rem;word-break:break-word;white-space:pre-wrap">{{ is_scalar($z['alt']) || $z['alt'] === null ? ($z['alt'] ?? '') : json_encode($z['alt'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</td>
                            <td style="padding:.375rem .5rem;word-break:break-word;white-space:pre-wrap">{{ is_scalar($z['neu']) || $z['neu'] === null ? ($z['neu'] ?? '') : json_encode($z['neu'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if ($eintrag->subject_type)
        <p style="margin-top:.75rem;color:rgb(107 114 128);font-size:.75rem">{{ $eintrag->typ() }} Nr. {{ $eintrag->subject_id }}, Quelle: {{ $eintrag->getProperty('quelle', 'web') }}</p>
    @endif
</div>

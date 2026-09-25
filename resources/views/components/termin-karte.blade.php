{{-- Dunkle Karte fuer einen Call: Etikett, Titel, Zeit, Zoom oder Aufzeichnung, eigener Status --}}
@props(['termin', 'etikett' => null, 'status' => true])
@php
    $mein = $status ? $termin->attendees->first() : null;
    $live = $termin->isLive();
    $vorbei = $termin->isPast();
    $etikett ??= $live ? 'Jetzt live' : ($vorbei ? 'Call dieser Woche' : 'Nächster Call');
@endphp
<a href="{{ route('termine.show', $termin) }}" {{ $attributes->merge(['class' => 'karte karte-dunkel block no-underline']) }}>
    <span class="eyebrow">{{ $etikett }}</span>
    <span class="karte-dunkel-titel">{{ $termin->title }}</span>
    <span class="m">{{ \App\Support\Zeit::wann($termin->starts_at) }}</span>
    <span class="flex flex-wrap gap-2 mt-3">
        @if (! $vorbei && $termin->zoom_url)
            <span class="knopf knopf-klein" onclick="event.preventDefault();window.open('{{ $termin->zoom_url }}','_blank','noopener')"><i class="fa-solid fa-video"></i>{{ $live ? 'Jetzt beitreten' : 'Zoom-Link' }}</span>
        @elseif ($termin->hasRecording())
            <span class="knopf knopf-klein"><i class="fa-solid fa-circle-play"></i>Aufzeichnung ansehen</span>
        @elseif ($vorbei)
            <span class="chip chip-hell">Aufzeichnung folgt</span>
        @endif
        @if ($mein && in_array($mein->status, ['attended', 'watched'], true))
            <span class="chip chip-gut"><i class="fa-solid fa-check"></i>{{ $mein->status === 'attended' ? 'Live dabei' : 'Gesehen' }}</span>
        @endif
    </span>
</a>

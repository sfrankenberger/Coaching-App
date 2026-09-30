{{-- Ein Termin, ueberall dieselben Aktionen (Bauregel 1): Zoom, In meinen Kalender, bei gebuchten 1:1 Verschieben und Absagen --}}
@props(['event', 'klein' => false, 'buchung' => null])
@php
    $ich = auth()->user();
    $vorbei = $event->isPast();
    $live = $event->isLive();
    if ($buchung === null && $event->isOneOnOne() && ! $vorbei) {
        $buchung = \App\Models\Booking::where('event_id', $event->id)->where('status', 'gebucht')
            ->when(! $ich->canManageCurrentTenant(), fn ($q) => $q->where('user_id', $ich->id))->first();
    }
    $k = $klein ? 'knopf-klein' : '';
@endphp
<span {{ $attributes->merge(['class' => 'termin-aktionen flex flex-wrap gap-2']) }}>
    @if (! $vorbei && $event->zoom_url)
        <a href="{{ $event->zoom_url }}" target="_blank" rel="noopener" class="knopf {{ $k }}"><i class="fa-solid fa-video"></i>{{ $live ? 'Jetzt beitreten' : 'Zoom öffnen' }}</a>
    @endif
    @if (! $vorbei)
        <a href="{{ \App\Support\Ics::googleUrl($event) }}" target="_blank" rel="noopener" class="knopf knopf-ruhig {{ $k }}" title="In Google Calendar eintragen"><i class="fa-brands fa-google"></i></a>
        <a href="{{ \App\Support\Ics::outlookUrl($event) }}" target="_blank" rel="noopener" class="knopf knopf-ruhig {{ $k }}" title="In Outlook eintragen"><i class="fa-brands fa-microsoft"></i></a>
        <a href="{{ route('termine.ics', $event) }}" class="knopf knopf-ruhig {{ $k }}"><i class="fa-solid fa-calendar-plus"></i>In meinen Kalender</a>
    @endif
    @if ($buchung && $buchung->istAktiv() && ! $vorbei)
        @if ($buchung->type && app(\App\Booking\GoogleCalendar::class)->aktiv())
            <a href="{{ route('buchen.verschieben', $buchung) }}" class="knopf knopf-leise {{ $k }}"><i class="fa-solid fa-arrows-rotate"></i>Verschieben</a>
        @endif
        <form method="post" action="{{ route('buchen.absagen', $buchung) }}" onsubmit="return confirm('Diesen Termin absagen?')">@csrf<button class="knopf knopf-leise {{ $k }}"><i class="fa-solid fa-calendar-xmark"></i>Absagen</button></form>
    @endif
</span>

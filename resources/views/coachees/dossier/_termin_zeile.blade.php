@php $e = $z['event']; $b = $z['buchung'] ?? null; $mit = $b && ($b->answers || $b->anhaenge->isNotEmpty() || $b->herkunft); @endphp
<a href="{{ route('termine.show', $e) }}" class="zeile">
    <span class="ic"><i class="fa-{{ $z['art'] === '1:1' ? 'solid fa-user' : 'solid fa-users' }}"></i></span>
    <span class="tx"><b>{{ $e->title }}</b>
        <span>{{ \App\Support\Zeit::wann($e->starts_at) }} · {{ $z['art'] }}@if ($z['status']) · {{ $z['status'] }}@endif @if ($e->recording_url) · Aufzeichnung @endif@if ($b?->herkunft) · kam über {{ $b->herkunft }}@endif</span>
    </span>
    <i class="fa-solid fa-chevron-right pf"></i>
</a>
@if ($mit)
    <details class="ml-3 mb-2">
        <summary class="hinweis cursor-pointer"><i class="fa-solid fa-feather"></i> Vorab mitgegeben</summary>
        @foreach ($b->answers ?? [] as $a)
            <p class="m-0 mt-1.5 text-md"><b class="block">{{ $a['frage'] ?? '' }}</b><span class="lesetext whitespace-pre-line">{{ $a['antwort'] ?? '' }}</span></p>
        @endforeach
        <x-anhaenge :item="$b" />
    </details>
@endif

@php $e = $z['event']; @endphp
<a href="{{ route('termine.show', $e) }}" class="zeile">
    <span class="ic"><i class="fa-{{ $z['art'] === '1:1' ? 'solid fa-user' : 'solid fa-users' }}"></i></span>
    <span class="tx"><b>{{ $e->title }}</b>
        <span>{{ \App\Support\Zeit::wann($e->starts_at) }} · {{ $z['art'] }}@if ($z['status']) · {{ $z['status'] }}@endif @if ($e->recording_url) · Aufzeichnung @endif</span>
    </span>
    <i class="fa-solid fa-chevron-right pf"></i>
</a>

@php $u = $z['user']; $m = $z['membership']; @endphp
<a href="{{ $z['dossier'] }}" class="zeile coachee-karte">
    <span class="ic" style="font-family:var(--font-heading);font-size:17px">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
    <span class="tx">
        <b style="white-space:normal">{{ $u->name }}
            @if ($z['wartet'])<span class="badge badge-wartet">wartet</span>@endif
            @if ($z['test'])<span class="badge badge-rec">Test</span>@endif
            @if (! $z['begleitet'])<span class="badge badge-rec">Kontakt</span>@endif
        </b>
        <span><i class="fa-regular fa-calendar"></i> {{ $z['naechster'] ? \App\Support\Zeit::wannKurz($z['naechster']->starts_at).' · '.\Illuminate\Support\Str::limit($z['naechster']->title, 34) : 'kein Termin' }}</span>
        <span><i class="fa-solid fa-list-check"></i> {{ $z['aufgaben'][0] }} von {{ $z['aufgaben'][1] }}
            @if ($z['kontingent']) · <i class="fa-solid fa-ticket"></i> {{ $z['kontingent']['offen'] }} von {{ $z['kontingent']['gesamt'] }} offen @endif
            · <i class="fa-solid fa-right-to-bracket"></i> {{ $z['zuletzt'] ? \App\Support\Zeit::relativ($z['zuletzt']) : 'nie' }}</span>
    </span>
    <i class="fa-solid fa-chevron-right pf"></i>
</a>

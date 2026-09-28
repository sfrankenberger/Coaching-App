@if ($antworten->isEmpty() && $reflexionen->isEmpty() && $notizen->isEmpty())
    <x-leer icon="share-from-square">{{ $person->vorname() }} hat noch nichts mit dir geteilt.</x-leer>
@endif

@foreach ($antworten as $unitId => $gruppe)
    @php $u = $gruppe->first()->exercise->unit; @endphp
    <div class="karte">
        <span class="eyebrow">{{ $u->program?->title }} · {{ $u->title }}</span>
        @foreach ($gruppe->sortBy(fn ($a) => $a->exercise->position) as $a)
            <p class="m-0 mt-2 text-md"><b>{{ $a->exercise->prompt ?: $a->exercise->title }}</b></p>
            <p class="lesetext text-md mt-1 mb-0 whitespace-pre-line">{{ $a->asText() }}</p>
            @include('coachees.dossier._kommentar', ['item' => $a, 'typ' => 'answer'])
        @endforeach
    </div>
@endforeach

@foreach ($reflexionen as $r)
    <div class="karte">
        <span class="eyebrow">Reflexion · {{ $r->week_label }} · {{ \App\Support\Zeit::relativ($r->shared_at ?? $r->created_at) }}</span>
        @foreach (['went_well' => 'Was gut lief', 'challenges' => 'Was schwer war', 'focus' => 'Fokus', 'addendum' => 'Nachtrag'] as $f => $l)
            @if (filled($r->$f))<p class="m-0 mt-2 text-md"><b>{{ $l }}</b></p><p class="lesetext text-md mt-1 mb-0 whitespace-pre-line">{{ $r->$f }}</p>@endif
        @endforeach
        @include('coachees.dossier._kommentar', ['item' => $r, 'typ' => 'reflection'])
    </div>
@endforeach

@foreach ($notizen as $n)
    <div class="karte">
        <span class="eyebrow">Notiz · {{ \App\Support\Zeit::relativ($n->created_at) }}</span>
        @if ($n->title)<p class="m-0 mt-2 text-md"><b>{{ $n->title }}</b></p>@endif
        <p class="lesetext text-md mt-1 mb-0 whitespace-pre-line">{{ $n->body }}</p>
        @include('coachees.dossier._kommentar', ['item' => $n, 'typ' => 'note'])
    </div>
@endforeach

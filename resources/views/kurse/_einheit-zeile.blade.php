@php $videos = count($unit->videoList()); $teile = $unit->exercises?->filter(fn ($e) => $e->isAnswerable())->count() ?? 0; $proz = $erledigt || ! $videos ? null : app(\App\Support\Medienstand::class)->prozent(auth()->user(), 'unit-'.$unit->id); @endphp
<a href="{{ route('kurse.einheit', [$program, $unit]) }}" @class(['lektion', 'erledigt' => $erledigt])>
    <span class="lektion-ic">
        @if ($erledigt)<i class="fa-solid fa-circle-check"></i>@elseif ($unit->type === 'exercise_set')<i class="fa-solid fa-pen-to-square"></i>@else<i class="fa-solid fa-circle-play"></i>@endif
    </span>
    <span class="lektion-t">{{ $unit->title }}@if ($unit->is_core) <span class="chip chip-coach" style="margin-left:4px">Kern</span>@endif</span>
    <span class="lektion-m">
        @if ($videos){{ $videos }} {{ $videos === 1 ? 'Video' : 'Videos' }}@if ($proz) · {{ $proz }} % gesehen @endif@elseif ($teile){{ $teile }} {{ $teile === 1 ? 'Frage' : 'Fragen' }}@elseif ($unit->duration_minutes){{ $unit->duration_minutes }} Min.@endif
        @if ($proz)<span class="stand-balken"><span style="width:{{ $proz }}%"></span></span>@endif
    </span>
    <i class="fa-solid fa-arrow-right lektion-pf"></i>
</a>

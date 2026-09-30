{{-- Reaktionen an einem geteilten Eintrag (Notiz, Aufgabe, Reflexion), einer Frage oder das Herz an einer Antwort. Eigene Eintraege zeigen nur die Zahlen. --}}
@props(['item', 'typ', 'nur' => null, 'klein' => false])
@php
    $gruppen = ($item->relationLoaded('reactions') ? $item->reactions : $item->reactions()->get())->groupBy('emoji');
    $ich = auth()->id();
    $eigene = (int) $item->user_id === $ich;
    $liste = $nur ? array_intersect_key(\App\Models\Reaction::EMOJIS, array_flip($nur)) : \App\Models\Reaction::EMOJIS;
@endphp
<div @class(['flex flex-wrap items-center gap-1', 'mt-2' => ! $klein, 'inline-flex' => $klein]) data-reaktionen-allgemein>
    @foreach ($liste as $key => [$emoji, $titel])
        @php $n = $gruppen->get($key)?->count() ?? 0; $mein = $gruppen->get($key)?->contains('user_id', $ich) ?? false; @endphp
        @if ($eigene && ! $n) @continue @endif
        <form method="post" action="{{ route('reaktion', [$typ, $item->getKey()]) }}" class="inline" data-reaktion-allgemein>
            @csrf
            <input type="hidden" name="emoji" value="{{ $key }}">
            <button type="{{ $eigene ? 'button' : 'submit' }}" @class(['reaktion', 'reaktion-klein' => $klein, 'an' => $mein, 'opacity-60' => ! $n && ! $mein]) title="{{ $titel }}" @disabled($eigene)>{{ $emoji }}@if ($n) <span class="z">{{ $n }}</span>@endif</button>
        </form>
    @endforeach
</div>

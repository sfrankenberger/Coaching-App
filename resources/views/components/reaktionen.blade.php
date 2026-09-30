{{-- Die vier Reaktionen an einem geteilten Eintrag (Notiz, Aufgabe, Reflexion). Eigene Eintraege zeigen nur die Zahlen. --}}
@props(['item', 'typ'])
@php
    $gruppen = ($item->relationLoaded('reactions') ? $item->reactions : $item->reactions()->get())->groupBy('emoji');
    $ich = auth()->id();
    $eigene = (int) $item->user_id === $ich;
@endphp
<div class="mt-2 flex flex-wrap items-center gap-1" data-reaktionen-allgemein>
    @foreach (\App\Models\Reaction::EMOJIS as $key => [$emoji, $titel])
        @php $n = $gruppen->get($key)?->count() ?? 0; $mein = $gruppen->get($key)?->contains('user_id', $ich) ?? false; @endphp
        @if ($eigene && ! $n) @continue @endif
        <form method="post" action="{{ route('reaktion', [$typ, $item->getKey()]) }}" class="inline" data-reaktion-allgemein>
            @csrf
            <input type="hidden" name="emoji" value="{{ $key }}">
            <button type="{{ $eigene ? 'button' : 'submit' }}" @class(['reaktion', 'an' => $mein, 'opacity-60' => ! $n && ! $mein]) title="{{ $titel }}" @disabled($eigene)>{{ $emoji }}@if ($n) <span class="z">{{ $n }}</span>@endif</button>
        </form>
    @endforeach
</div>

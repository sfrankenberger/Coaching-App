@php $gruppen = $m->reactions->groupBy('emoji'); $ich = auth()->id(); @endphp
<div class="mt-1 flex flex-wrap items-center gap-1" data-reaktionen="{{ $m->id }}">
    @foreach (\App\Models\Reaction::EMOJIS as $key => [$emoji, $titel])
        @php $n = $gruppen->get($key)?->count() ?? 0; $mein = $gruppen->get($key)?->contains('user_id', $ich) ?? false; @endphp
        @if ($eigene && ! $n) @continue @endif
        <form method="post" action="{{ route('nachricht.reaktion', $m) }}" class="inline" data-reaktion>
            @csrf
            <input type="hidden" name="emoji" value="{{ $key }}">
            <button type="{{ $eigene ? 'button' : 'submit' }}" @class(['rounded-full border px-1.5 py-0.5 text-xs leading-none', 'border-primary bg-primary-tint' => $mein, 'border-transparent' => ! $mein && $n, 'border-transparent opacity-40 hover:opacity-100' => ! $n]) title="{{ $titel }}" @disabled($eigene)>{{ $emoji }}@if ($n) <span class="ml-0.5">{{ $n }}</span>@endif</button>
        </form>
    @endforeach
</div>

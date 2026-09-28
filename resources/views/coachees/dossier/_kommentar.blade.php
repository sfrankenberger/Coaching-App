@php $liste = $item->relationLoaded('comments') ? $item->comments->sortBy('created_at') : collect(); @endphp
<div class="kommentare mt-2">
    @foreach ($liste as $c)
        <div class="kommentar {{ $c->user_id !== $item->user_id ? 'vom-team' : '' }}">
            <span class="wer">{{ $c->user_id === auth()->id() ? 'Du' : $c->user?->vorname() }} · {{ \App\Support\Zeit::wannKurz($c->created_at) }}</span>
            <span class="lesetext whitespace-pre-line text-md">{{ $c->body }}</span>
        </div>
    @endforeach
    <form method="post" action="{{ route('coachees.kommentar', $m) }}" class="kommentar-form">
        @csrf<input type="hidden" name="typ" value="{{ $typ }}"><input type="hidden" name="id" value="{{ $item->getKey() }}">
        <input type="text" name="text" placeholder="Antworten ..." class="kommentar-feld" required maxlength="5000">
        <button type="submit" class="kommentar-senden" aria-label="Senden"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
</div>

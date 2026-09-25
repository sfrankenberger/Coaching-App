@props(['item'])
@php
    $k = app(\App\Coach\Kommentare::class);
    $liste = $item->comments()->with('user:id,name')->oldest()->get();
    $ich = auth()->user();
@endphp
@if ($liste->isNotEmpty() || $k->geteilt($item))
    <div class="kommentare">
        @foreach ($liste as $c)
            <div id="kommentar-{{ $c->id }}" @class(['kommentar', 'vom-team' => $c->user_id !== $item->user_id])>
                <span class="wer">{{ $c->user_id === $ich->id ? 'Du' : $c->user?->vorname() }} · {{ $c->created_at->translatedFormat('j. M, H:i') }}</span>
                <span class="whitespace-pre-line">{{ $c->body }}</span>
                @if ($c->user_id === $ich->id)
                    <form method="post" action="{{ route('kommentar.destroy', $c) }}" onsubmit="return confirm('Kommentar löschen?')" class="weg">@csrf @method('DELETE')<button aria-label="Kommentar löschen"><i class="fa-solid fa-xmark"></i></button></form>
                @endif
            </div>
        @endforeach
        @if ($k->darf($ich, $item))
            <details @if ($liste->isNotEmpty()) open @endif>
                <summary class="hinweis cursor-pointer"><i class="fa-regular fa-comment"></i> {{ $liste->isEmpty() ? 'Kommentieren' : 'Antworten' }}</summary>
                <form method="post" action="{{ route('kommentar.store') }}" class="flex gap-2" style="margin-top:8px">
                    @csrf
                    <input type="hidden" name="typ" value="{{ $k->typ($item) }}">
                    <input type="hidden" name="id" value="{{ $item->getKey() }}">
                    <textarea name="body" rows="1" class="feld" required maxlength="5000" placeholder="Schreib etwas dazu ..."></textarea>
                    <button class="knopf" style="flex:none;width:44px;padding:0" aria-label="Senden"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </details>
        @endif
    </div>
@endif

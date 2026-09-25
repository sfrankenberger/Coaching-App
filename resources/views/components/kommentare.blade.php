{{-- Kommentare unter einem geteilten Eintrag. stil "app" (Formular per POST) oder "coach" (Dossier, Livewire) --}}
@props(['item', 'stil' => 'app', 'typ' => null])
@php
    $k = app(\App\Coach\Kommentare::class);
    $typ ??= $k->typ($item);
    $liste = $item->relationLoaded('comments') ? $item->comments->sortBy('created_at') : $item->comments()->with('user:id,name')->oldest()->get();
    $ich = auth()->user();
    $coach = $stil === 'coach';
@endphp
@if ($liste->isNotEmpty() || $k->darf($ich, $item))
    <div @class(['kommentare', 'kommentare-coach' => $coach])>
        @foreach ($liste as $c)
            <div id="kommentar-{{ $c->id }}" @class(['kommentar', 'vom-team' => $c->user_id !== $item->user_id])>
                <span class="wer">{{ $c->user_id === $ich->id ? 'Du' : $c->user?->vorname() }} · {{ \App\Support\Zeit::wannKurz($c->created_at) }}</span>
                <span class="whitespace-pre-line">{{ $c->body }}</span>
                @if (! $coach && $c->user_id === $ich->id)
                    <form method="post" action="{{ route('kommentar.destroy', $c) }}" onsubmit="return confirm('Kommentar löschen?')" class="weg">@csrf @method('DELETE')<button aria-label="Kommentar löschen"><i class="fa-solid fa-xmark"></i></button></form>
                @endif
            </div>
        @endforeach
        @if ($k->darf($ich, $item))
            @if ($coach)
                <form wire:submit="antworten('{{ $typ }}', {{ $item->getKey() }})" class="kommentar-form">
                    <input type="text" wire:model="antwort.{{ $typ }}-{{ $item->getKey() }}" placeholder="Antworten ..." class="kommentar-feld">
                    <button type="submit" class="kommentar-senden" aria-label="Senden"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            @else
                <details @if ($liste->isNotEmpty()) open @endif>
                    <summary class="hinweis cursor-pointer"><i class="fa-regular fa-comment"></i> {{ $liste->isEmpty() ? 'Kommentieren' : 'Antworten' }}</summary>
                    <form method="post" action="{{ route('kommentar.store') }}" class="kommentar-form">
                        @csrf
                        <input type="hidden" name="typ" value="{{ $typ }}">
                        <input type="hidden" name="id" value="{{ $item->getKey() }}">
                        <textarea name="body" rows="1" class="feld" required maxlength="5000" placeholder="Schreib etwas dazu ..."></textarea>
                        <button type="submit" class="knopf kommentar-senden" aria-label="Senden"><i class="fa-solid fa-paper-plane"></i></button>
                    </form>
                </details>
            @endif
        @endif
    </div>
@endif

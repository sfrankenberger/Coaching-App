<p class="hinweis">Nur für dich und dein Team. {{ $person->vorname() }} sieht das nie. Diktieren geht mit dem Mikrofon.</p>
<form method="post" action="{{ route('coachees.notiz', $m) }}" class="karte">
    @csrf
    <textarea name="body" rows="3" class="feld" placeholder="Was dir auffällt, was du nicht vergessen willst ..." required>{{ old('body') }}</textarea>
    <button type="submit" class="knopf knopf-klein">Notiz speichern</button>
</form>
@forelse ($coachNotizen as $cn)
    <div class="karte">
        <span class="eyebrow">@if ($cn->is_pinned)<i class="fa-solid fa-thumbtack"></i> @endif{{ \App\Support\Zeit::wannKurz($cn->created_at) }}{{ $cn->author ? ' · '.$cn->author->vorname() : '' }}</span>
        <p class="lesetext text-md mt-1 mb-0 whitespace-pre-line">{{ $cn->body }}</p>
        <form method="post" action="{{ route('coachees.notiz.loeschen', [$m, $cn->id]) }}" onsubmit="return confirm('Notiz löschen?')" class="mt-1">@csrf @method('DELETE')<button type="submit" class="knopf knopf-text knopf-klein">Löschen</button></form>
    </div>
@empty
    <p class="hinweis">Noch keine Notiz.</p>
@endforelse

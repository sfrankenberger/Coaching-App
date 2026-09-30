{{-- Eine Antwort auf eine Frage, mit Herz, Antwort darauf, Bearbeiten (15 Min), Loeschen, beste Antwort. --}}
@props(['a', 'frage', 'team', 'seit', 'kind' => false])
@php
    $ich = auth()->user();
    $verwaltet = $ich->canManageCurrentTenant();
    $vomTeam = $team->contains($a->user_id);
    $neu = $seit && $a->user_id !== $ich->id && $a->created_at->gt($seit);
    $lang = \App\Support\Textform::lang($a->body);
@endphp
<article id="antwort-{{ $a->id }}" @class(['karte antwort', 'vom-team' => $vomTeam, 'kind' => $kind, 'beste' => $a->is_best, 'neu-seit' => $neu]) data-antwort-id="{{ $a->id }}">
    @if ($a->is_best)<span class="beste-marke"><i class="fa-solid fa-star"></i> Das ist die Antwort</span>@endif
    <span class="flex items-center gap-2 mb-1">
        <x-avatar :user="$a->user" :size="28" />
        <span class="t text-md">{{ $a->user_id === $ich->id ? 'Du' : $a->user?->name }}</span>
        @if ($vomTeam)<span class="chip chip-coach">Team</span>@endif
        @if ($neu)<span class="chip chip-neu">Neu</span>@endif
        <span class="m ml-auto">{{ $a->created_at->translatedFormat('j. M, H:i') }}@if ($a->edited_at) · geändert @endif</span>
    </span>
    <div @class(['x lesetext m-0 text-base', 'weiterlesen' => $lang]) @if ($lang) data-weiterlesen @endif>{!! \App\Support\Textform::lebendig($a->body) !!}</div>
    @if ($lang)<button type="button" class="knopf knopf-text knopf-klein" data-weiterlesen-knopf>Weiterlesen</button>@endif
    <div class="flex flex-wrap items-center gap-2 mt-2">
        <x-reaktionen :item="$a" typ="comment" :nur="['herz']" :klein="true" />
        @if (! $kind && ! $frage->istZu())
            <button type="button" class="knopf knopf-text knopf-klein" data-antwort-auf="{{ $a->id }}" data-name="{{ $a->user?->vorname() }}"><i class="fa-solid fa-reply"></i>Antworten</button>
        @endif
        @if ($verwaltet && ! $kind)
            <form method="post" action="{{ route('fragen.antwort.beste', $a) }}">@csrf<button type="submit" class="knopf knopf-text knopf-klein"><i class="fa-{{ $a->is_best ? 'solid' : 'regular' }} fa-star"></i>{{ $a->is_best ? 'Markierung weg' : 'Beste Antwort' }}</button></form>
        @endif
        @if ($a->bearbeitbarFuer($ich))
            <details class="ml-auto"><summary class="knopf knopf-text knopf-klein cursor-pointer list-none"><i class="fa-solid fa-pen"></i>Bearbeiten</summary>
                <form method="post" action="{{ route('fragen.antwort.aendern', $a) }}" class="eingabe mt-2">@csrf @method('PATCH')<textarea name="body" class="feld" rows="3" required maxlength="10000" data-ohne-diktat>{{ $a->body }}</textarea><div class="eingabe-knoepfe"><button type="submit" class="knopf knopf-klein">Speichern</button></div></form>
            </details>
        @endif
        @if ($a->user_id === $ich->id || $verwaltet)
            <form method="post" action="{{ route('fragen.antwort.loeschen', $a) }}" onsubmit="return confirm('Antwort löschen?')" @class(['ml-auto' => ! $a->bearbeitbarFuer($ich)])>@csrf @method('DELETE')<button type="submit" class="knopf knopf-text knopf-klein"><i class="fa-solid fa-trash"></i></button></form>
        @endif
    </div>
    @if (! $kind && $a->relationLoaded('children') && $a->children->isNotEmpty())
        <div class="antwort-kinder">
            @foreach ($a->children as $k)
                @include('fragen._antwort', ['a' => $k, 'frage' => $frage, 'team' => $team, 'seit' => $seit, 'kind' => true])
            @endforeach
        </div>
    @endif
</article>

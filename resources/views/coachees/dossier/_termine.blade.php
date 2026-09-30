@php $zeile = fn ($z) => view('coachees.dossier._termin_zeile', ['z' => $z])->render(); @endphp
<h3 class="abschnitt"><i class="fa-regular fa-calendar"></i>Kommt<em>{{ $kommt->count() }}</em></h3>
@forelse ($kommt as $z)
    {!! $zeile($z) !!}
@empty
    <p class="hinweis">Nichts geplant.</p>
@endforelse

<div class="flex gap-2 flex-wrap mt-2 mb-3">
    <a href="#zeiten" class="knopf knopf-leise knopf-klein" data-aufklappen="zeiten"><i class="fa-regular fa-clock"></i>Zeiten vorschlagen</a>
    <a href="#termin" class="knopf knopf-leise knopf-klein" data-aufklappen="termin"><i class="fa-solid fa-calendar-plus"></i>Termin eintragen</a>
</div>

<details id="zeiten" class="karte" @if ($errors->has('zeiten')) open @endif>
    <summary class="cursor-pointer" style="font-weight:600">Zeiten vorschlagen</summary>
    <p class="hinweis mt-1">{{ $person->vorname() }} sieht die Zeiten im Gespräch und tippt eine an. Daraus wird der Termin, mit Kalendereintrag und Bestätigung.</p>
    @if (($freieZeiten ?? collect())->isNotEmpty())
        <form method="post" action="{{ route('coachees.vorschlag', $m) }}" class="mb-3">
            @csrf
            @foreach ($freieZeiten as $z)<input type="hidden" name="zeiten[]" value="{{ $z->format('Y-m-d\TH:i') }}">@endforeach
            <input type="hidden" name="dauer" value="60">
            <input type="hidden" name="text" value="Hallo {{ $person->vorname() }}, diese Zeiten hätte ich für unser nächstes Gespräch. Tipp einfach die an, die dir passt.">
            <button type="submit" class="knopf knopf-klein"><i class="fa-solid fa-wand-magic-sparkles"></i>Drei freie Zeiten vorschlagen: {{ $freieZeiten->map(fn ($z) => $z->translatedFormat('D j.n., H:i'))->join(' · ') }}</button>
        </form>
    @endif
    <form method="post" action="{{ route('coachees.vorschlag', $m) }}">
        @csrf
        @for ($i = 0; $i < 3; $i++)
            <label class="feld-label">Zeit {{ $i + 1 }}<input type="datetime-local" name="zeiten[]" class="feld" @if ($i === 0) required @endif></label>
        @endfor
        <label class="feld-label">Dauer in Minuten<input type="number" name="dauer" class="feld" value="60" min="15" max="240" required></label>
        <label class="feld-label">Nachricht dazu<textarea name="text" rows="2" class="feld" data-ohne-diktat>Hallo {{ $person->vorname() }}, diese Zeiten hätte ich für unser nächstes Gespräch. Tipp einfach die an, die dir passt.</textarea></label>
        <button type="submit" class="knopf knopf-klein">Ins Gespräch schicken</button>
    </form>
</details>

<details id="termin" class="karte mt-2" @if ($errors->has('start')) open @endif>
    <summary class="cursor-pointer" style="font-weight:600">Termin eintragen (1:1)</summary>
    <form method="post" action="{{ route('coachees.termin', $m) }}" class="mt-2">
        @csrf
        <label class="feld-label">Wann<input type="datetime-local" name="start" class="feld" required></label>
        <label class="feld-label">Dauer in Minuten<input type="number" name="dauer" class="feld" value="60" min="15" max="240" required></label>
        <label class="feld-label">Titel<input type="text" name="title" class="feld" placeholder="Einzelsitzung"></label>
        <button type="submit" class="knopf knopf-klein">Eintragen, {{ $person->vorname() }} bekommt Bescheid</button>
    </form>
</details>

<h3 class="abschnitt mt-4"><i class="fa-solid fa-clock-rotate-left"></i>War<em>{{ $war->count() }}</em></h3>
@forelse ($war as $z)
    {!! $zeile($z) !!}
@empty
    <p class="hinweis">Noch nichts.</p>
@endforelse

<x-layouts.app title="Wochenreflexion">
    <p style="margin:0 0 8px"><a href="{{ route('journal.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Mein Journal</a></p>
    <h1 style="margin-bottom:4px">Wochenreflexion</h1>
    <p class="unterzeile" style="margin:0 0 14px">Nimm dir zehn Minuten. Deine Antworten bleiben bei dir; wenn du magst, gehen sie zusätzlich an deine Coachin.</p>

    <x-karte>
        <form method="post" action="{{ route('reflexion.store') }}" class="eingabe">
            @csrf
            <input type="hidden" name="refl_id" value="{{ $entwurf?->id }}">
            <p class="hinweis">{{ $entwurf?->week_label ?? $woche }}</p>
            @foreach ($fragen as $k => [$ico, $frage, $tipp])
                <div>
                    <label for="refl-{{ $k }}" class="block font-heading" style="font-size:var(--fs-xl);line-height:1.3">{{ $ico }} {{ $frage }}</label>
                    <span class="hinweis block mb-1">{{ $tipp }}</span>
                    <textarea id="refl-{{ $k }}" name="{{ $k }}" rows="4" class="feld" placeholder="Schreib oder diktiere ...">{{ old($k, $entwurf?->$k) }}</textarea>
                </div>
            @endforeach
            <div class="flex flex-wrap gap-2">
                @if ($kurse->count())
                    <label class="block"><span class="feld-label">Kurs</span><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $entwurf?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="block"><span class="feld-label">Wer sieht das?</span><select name="visibility" class="feld">
                    <option value="private">Nur ich</option>
                    <option value="coach">Meine Coachin</option>
                    @if ($kurse->count())<option value="program">Mein Kurs</option>@endif
                </select></label>
            </div>
            @if ($entwurf)
                <p class="hinweis">Du hast am {{ $entwurf->created_at->translatedFormat('j. F, H:i') }} Uhr angefangen. Schreib einfach weiter.</p>
            @endif
            <div class="eingabe-knoepfe"><button type="submit" class="knopf knopf-breit">{{ $entwurf ? 'Reflexion weiter speichern' : 'Reflexion speichern' }}</button></div>
        </form>
    </x-karte>

    @if ($meine->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-clock-rotate-left"></i>Deine bisherigen Reflexionen<em>{{ $meine->count() }}</em></h2>
        @foreach ($meine as $r)
            <article id="reflexion-{{ $r->id }}" class="karte">
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <span class="hinweis">{{ $r->week_label ?: $r->created_at->translatedFormat('j. F Y') }}@if ($r->program) · {{ $r->program->title }}@endif · {{ $r->isShared() ? 'Geteilt' : 'Nur ich' }}</span>
                        @foreach ($fragen as $k => [$ico, $frage])
                            @if ($r->$k)<p class="mt-2"><b class="block text-md">{{ $ico }} {{ $frage }}</b><span class="lesetext whitespace-pre-line">{{ $r->$k }}</span></p>@endif
                        @endforeach
                        @if ($r->addendum)<p class="mt-2"><b class="block text-md">Nachtrag</b><span class="lesetext whitespace-pre-line">{{ $r->addendum }}</span></p>@endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($r->isShared())
                                <details class="w-full">
                                    <summary class="hinweis cursor-pointer">Nachtrag schreiben</summary>
                                    <form method="post" action="{{ route('reflexion.nachtrag', $r) }}" class="eingabe mt-2">@csrf<textarea name="addendum" class="feld" rows="3" required placeholder="Was dir noch einfällt ..."></textarea><div class="eingabe-knoepfe"><button class="knopf knopf-leise">Nachtrag speichern</button></div></form>
                                </details>
                            @else
                                <a href="{{ route('reflexion.index', ['refl' => $r->id]) }}" class="knopf knopf-leise knopf-klein">Weiterschreiben</a>
                                <form method="post" action="{{ route('reflexion.teilen', $r) }}">@csrf<input type="hidden" name="visibility" value="coach"><button class="knopf knopf-leise knopf-klein">Mit Coachin teilen</button></form>
                            @endif
                        </div>
                        <x-kommentare :item="$r" />
                    </div>
                    <details class="relative shrink-0">
                        <summary class="list-none cursor-pointer knopf-rund grid place-items-center" aria-label="Mehr"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
                        <div class="menue" style="right:0;top:36px">
                            @if ($r->isShared())<form method="post" action="{{ route('reflexion.teilen', $r) }}">@csrf<input type="hidden" name="visibility" value="private"><button class="e"><i class="fa-solid fa-lock"></i>Nicht mehr teilen</button></form>@endif
                            <form method="post" action="{{ route('reflexion.destroy', $r) }}" onsubmit="return confirm('Reflexion löschen?')">@csrf @method('DELETE')<button class="e gefahr"><i class="fa-solid fa-trash"></i>Löschen</button></form>
                        </div>
                    </details>
                </div>
            </article>
        @endforeach
    @endif
</x-layouts.app>

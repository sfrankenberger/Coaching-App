<x-layouts.app title="Meine Projekte">
    <p class="m-0 mb-2"><a href="{{ route('journal.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Mein Journal</a></p>
    <h1 class="mb-1">Meine Projekte</h1>
    <p class="unterzeile m-0 mb-3">Vielleicht arbeitest du im Kurs oder im Coaching an einem bestimmten Thema. Vielleicht an mehreren gleichzeitig: an deinem Nebenerwerb, an deiner Gesundheit, an einer Entscheidung, die ansteht. Hier gibst du diesen Vorhaben einen Namen. Danach kannst du bei jeder Notiz, Aufgabe und Reflexion wählen, wozu sie gehört, und findest alles wieder.</p>

    <details class="baustein mb-3">
        <summary class="cursor-pointer" style="font-family:var(--font-heading);font-size:18px">Wo stehst du gerade? Die neun Schritte</summary>
        <p class="lesetext mt-2">Veränderung läuft selten geradeaus, aber sie folgt meist einem ähnlichen Bogen. {{ $coach }} arbeitet mit neun Schritten. Du kannst an jedem Projekt festhalten, wo du gerade stehst. Das ist freiwillig. Manche finden es hilfreich, weil es beim Zurückschauen Ordnung gibt und zeigt, dass eine zähe Phase dazugehört.</p>
        <ol class="schritte-liste">
            @foreach (\App\Models\Projekt::SCHRITTE as $k => [$name, $icon, $farbe, $hinweis])
                <li style="--pc: {{ $farbe }}"><i class="fa-solid fa-{{ $icon }}"></i><b>{{ $name }}</b><span>{{ $hinweis }}</span></li>
            @endforeach
        </ol>
        <p class="hinweis m-0">Du musst nichts davon auswählen. Wenn du magst, setzt du es beim Projekt und änderst es, wenn sich etwas bewegt.</p>
    </details>

    <div class="pillen">
        <a href="{{ route('projekte.index') }}" @class(['pille', 'an' => $ansicht === 'treppe'])>Wo ich stehe</a>
        <a href="{{ route('projekte.index', ['ansicht' => 'liste']) }}" @class(['pille', 'an' => $ansicht === 'liste'])>Bearbeiten</a>
    </div>

    @if ($ansicht === 'treppe')
        @php $nach = $projekte->groupBy(fn ($p) => $p->schritt && isset(\App\Models\Projekt::SCHRITTE[$p->schritt]) ? $p->schritt : ''); $nr = 0; @endphp
        <div class="treppe">
            @foreach (\App\Models\Projekt::SCHRITTE as $k => [$name, $icon, $farbe])
                @php $nr++; $hier = $nach->get($k, collect()); @endphp
                <section @class(['stufe', 'belegt' => $hier->isNotEmpty()]) style="--pc: {{ $farbe }}">
                    <div class="kopf"><span class="nr">{{ $nr }}</span><i class="fa-solid fa-{{ $icon }}"></i><span class="t">{{ $name }}</span>@if ($hier->isNotEmpty())<span class="z">{{ $hier->count() }}</span>@endif</div>
                    @foreach ($hier as $p)
                        @include('projekte._mini', ['p' => $p, 'hier' => $k])
                    @endforeach
                </section>
            @endforeach
            @if ($nach->get('', collect())->isNotEmpty())
                <section class="stufe belegt offen">
                    <div class="kopf"><span class="nr">·</span><i class="fa-regular fa-circle"></i><span class="t">Noch kein Schritt gesetzt</span><span class="z">{{ $nach->get('')->count() }}</span></div>
                    @foreach ($nach->get('') as $p)
                        @include('projekte._mini', ['p' => $p, 'hier' => ''])
                    @endforeach
                </section>
            @endif
        </div>
    @else
        @foreach ($projekte as $p)
            @php $st = $p->schrittInfo(); @endphp
            <article id="projekt-{{ $p->id }}" class="karte" style="--pc: {{ $p->farbe }}">
                <div class="flex items-start gap-3">
                    <span class="projekt-ic"><i class="fa-solid fa-{{ $p->icon }}"></i></span>
                    <div class="min-w-0 flex-1">
                        <span class="t">{{ $p->name }}</span>
                        <span class="m">{{ $st ? $st[0] : 'noch kein Schritt gesetzt' }} · {{ $p->notes_count + $p->tasks_count + $p->reflections_count }} Einträge · {{ \App\Models\Note::VISIBILITIES[$p->visibility] ?? '' }}</span>
                        @if ($p->worum)<p class="lesetext m-0 mt-1 text-md">{{ $p->worum }}</p>@endif
                        <div class="flex flex-wrap gap-2 mt-2 items-end">
                            <form method="post" action="{{ route('projekte.schritt', $p) }}">@csrf<label class="block"><span class="feld-label">Wo stehst du?</span><select name="schritt" class="feld" onchange="this.form.submit()"><option value="">noch offen</option>@foreach (\App\Models\Projekt::SCHRITTE as $k => [$name]) <option value="{{ $k }}" @selected($p->schritt === $k)>{{ $name }}</option>@endforeach</select></label></form>
                            <a href="{{ route('journal.index', ['projekt' => $p->id]) }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-timeline"></i>Einträge ansehen</a>
                        </div>
                        @if ($p->visibility !== 'private')<x-kommentare :item="$p" />@endif
                    </div>
                    <details class="relative shrink-0">
                        <summary class="list-none cursor-pointer knopf-rund grid place-items-center" aria-label="Mehr"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
                        <div class="menue" style="right:0;top:36px">
                            <a href="{{ route('projekte.index', ['ansicht' => 'liste', 'bearbeiten' => $p->id]) }}#neu" class="e"><i class="fa-solid fa-pen"></i>Bearbeiten</a>
                            <form method="post" action="{{ route('projekte.destroy', $p) }}" onsubmit="return confirm('Projekt entfernen? Die Einträge bleiben erhalten.')">@csrf @method('DELETE')<button class="e gefahr"><i class="fa-solid fa-trash"></i>Entfernen</button></form>
                        </div>
                    </details>
                </div>
            </article>
        @endforeach
        @if ($projekte->isEmpty())
            <x-leer icon="lightbulb">Noch kein Projekt. Leg eines an, dann siehst du unter «Wo ich stehe», wo es gerade steht.</x-leer>
        @endif

        <div id="neu" class="baustein mt-3">
            <p class="eyebrow m-0 mb-2.5">{{ $bearbeiten ? 'Projekt bearbeiten' : 'Projekt anlegen' }}</p>
            <form method="post" action="{{ $bearbeiten ? route('projekte.update', $bearbeiten) : route('projekte.store') }}" class="eingabe">
                @csrf
                <input name="name" class="feld" maxlength="80" required placeholder="Zum Beispiel: Mein Nebenerwerb" value="{{ old('name', $bearbeiten?->name) }}">
                @error('name')<p class="fehler m-0">{{ $message }}</p>@enderror
                <textarea name="worum" class="feld" rows="2" placeholder="Worum geht es? Ein Satz für dich selbst" data-ohne-diktat>{{ old('worum', $bearbeiten?->worum) }}</textarea>
                <div class="flex flex-wrap gap-4">
                    <div><span class="feld-label">Farbe</span><div class="farbwahl">@foreach (\App\Models\Projekt::FARBEN as $f)<label style="--pc: {{ $f }}"><input type="radio" name="farbe" value="{{ $f }}" class="sr-only" @checked(old('farbe', $bearbeiten?->farbe ?? \App\Models\Projekt::FARBEN[0]) === $f)><span></span></label>@endforeach</div></div>
                    <div><span class="feld-label">Symbol</span><div class="iconwahl">@foreach (\App\Models\Projekt::ICONS as $i)<label><input type="radio" name="icon" value="{{ $i }}" class="sr-only" @checked(old('icon', $bearbeiten?->icon ?? \App\Models\Projekt::ICONS[0]) === $i)><span><i class="fa-solid fa-{{ $i }}"></i></span></label>@endforeach</div></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label class="block"><span class="feld-label">Wo stehst du gerade?</span><select name="schritt" class="feld"><option value="">noch offen</option>@foreach (\App\Models\Projekt::SCHRITTE as $k => [$name]) <option value="{{ $k }}" @selected(old('schritt', $bearbeiten?->schritt) === $k)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block"><span class="feld-label">Wer sieht das?</span><select name="visibility" class="feld">
                        <option value="private" @selected(old('visibility', $bearbeiten?->visibility ?? 'private') === 'private')>Nur ich</option>
                        <option value="coach" @selected(old('visibility', $bearbeiten?->visibility) === 'coach')>Mit {{ $coach }} geteilt</option>
                        @if ($gemeinschaft->count())<option value="program" @selected(old('visibility', $bearbeiten?->visibility) === 'program')>Im Kurs sichtbar</option><option value="all" @selected(old('visibility', $bearbeiten?->visibility) === 'all')>In der Community</option>@endif
                    </select></label>
                    @if ($gemeinschaft->count())
                        <label class="block"><span class="feld-label">Gehört zu</span><select name="program_id" class="feld">@foreach ($gemeinschaft as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                    @endif
                </div>
                <div class="eingabe-knoepfe">
                    @if ($bearbeiten)<a href="{{ route('projekte.index', ['ansicht' => 'liste']) }}" class="knopf knopf-leise">Abbrechen</a>@endif
                    <button type="submit" class="knopf">{{ $bearbeiten ? 'Speichern' : 'Projekt anlegen' }}</button>
                </div>
            </form>
        </div>
    @endif
</x-layouts.app>

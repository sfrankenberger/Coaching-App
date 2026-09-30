{{-- Dreipunkt-Menue an Notiz, Aufgabe, Reflexion: Bearbeiten, Teilen mit, Anpinnen, Projekt, Loeschen --}}
@props(['item', 'typ', 'bearbeiten' => null, 'loeschen' => null, 'frage' => 'Eintrag löschen?'])
@php
    $ich = auth()->user();
    $eigen = (int) $item->user_id === $ich->id;
    $el = app(\App\Support\Elemente::class);
    $gemeinschaft = $eigen ? $el->gemeinschaft($ich) : [];
    $projekte = $eigen ? $el->projekte($ich) : collect();
    $schnell = route('element.schnell', [$typ, $item->getKey()]);
    $pinbar = $eigen && ! $item instanceof \App\Models\Reflection;
    $coach = app(\App\Tenancy\Branding::class)->coachName();
@endphp
@if ($eigen)
<details class="relative shrink-0">
    <summary class="list-none cursor-pointer knopf-rund grid place-items-center" aria-label="Mehr"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
    <div class="menue" style="right:0;top:36px">
        @if ($bearbeiten)<a href="{{ $bearbeiten }}" class="e"><i class="fa-solid fa-pen"></i>Bearbeiten</a>@endif
        @if ($pinbar)
            <form method="post" action="{{ $schnell }}">@csrf<input type="hidden" name="was" value="pin"><button class="e"><i class="fa-solid fa-thumbtack"></i>{{ $item->is_pinned ? 'Nicht mehr anpinnen' : 'Anpinnen' }}</button></form>
        @endif
        <details>
            <summary class="e list-none"><i class="fa-solid fa-share-nodes"></i>Teilen mit<i class="fa-solid fa-chevron-down ml-auto" style="font-size:10px"></i></summary>
            <form method="post" action="{{ $schnell }}" class="unter">
                @csrf<input type="hidden" name="was" value="teilen"><input type="hidden" name="sicht" value="">
                <button name="sicht" value="private" @class(['e', 'an' => $item->visibility === 'private'])><i class="fa-solid fa-lock"></i>Nur ich</button>
                <button name="sicht" value="coach" @class(['e', 'an' => $item->visibility === 'coach'])><i class="fa-solid fa-user"></i>{{ $coach }}</button>
                @foreach ($gemeinschaft as $id => $t)
                    <button name="kurs" value="{{ $id }}" onclick="this.form.sicht.value='program'" @class(['e', 'an' => $item->visibility === 'program' && (int) $item->program_id === $id])><i class="fa-solid fa-users"></i>{{ $t }}</button>
                @endforeach
                @if ($gemeinschaft)<button name="sicht" value="all" @class(['e', 'an' => $item->visibility === 'all'])><i class="fa-solid fa-globe"></i>Community</button>@endif
            </form>
        </details>
        @if ($projekte->isNotEmpty())
            <details>
                <summary class="e list-none"><i class="fa-solid fa-lightbulb"></i>Projekt<i class="fa-solid fa-chevron-down ml-auto" style="font-size:10px"></i></summary>
                <form method="post" action="{{ $schnell }}" class="unter">
                    @csrf<input type="hidden" name="was" value="projekt">
                    <button name="projekt" value="" @class(['e', 'an' => ! $item->project_id])><i class="fa-regular fa-circle"></i>ohne Projekt</button>
                    @foreach ($projekte as $p)
                        <button name="projekt" value="{{ $p->id }}" @class(['e', 'an' => (int) $item->project_id === $p->id]) style="--pc: {{ $p->farbe }}"><i class="fa-solid fa-{{ $p->icon }}" style="color:var(--pc)"></i>{{ $p->name }}</button>
                    @endforeach
                </form>
            </details>
        @endif
        @if ($loeschen)
            <form method="post" action="{{ $loeschen }}" onsubmit="return confirm('{{ $frage }}')">@csrf @method('DELETE')<button class="e gefahr"><i class="fa-solid fa-trash"></i>Löschen</button></form>
        @endif
    </div>
</details>
@endif

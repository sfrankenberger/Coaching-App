{{-- Einheitliche Filterleiste: Art, Zeit, Projekt, Kurs, Status, Suche. Eingeklappt mit Zusammenfassung. --}}
@props(['filter', 'arten' => [], 'status' => \App\Support\Filter::STATUS, 'projekte' => collect(), 'kurse' => [], 'platzhalter' => 'Suchen', 'ohneStatus' => false])
<details class="filterleiste" @if ($filter->aktiv()) open @endif>
    <summary class="cursor-pointer flex items-center gap-2 flex-wrap">
        <i class="fa-solid fa-sliders"></i>
        <span class="text-md">{{ $filter->zusammenfassung($arten, $projekte, $kurse) }}</span>
        @if ($filter->aktiv())<a href="{{ url()->current() }}" class="hinweis ml-auto">Zurücksetzen</a>@endif
    </summary>
    <form method="get" class="filter-form">
        @if ($arten)
            <label><span>Art</span><select name="art" class="feld" onchange="this.form.submit()"><option value="">Alles</option>@foreach ($arten as $k => $l)<option value="{{ $k }}" @selected($filter->art === $k)>{{ is_array($l) ? $l[0] : $l }}</option>@endforeach</select></label>
        @endif
        <label><span>Zeit</span><select name="zeit" class="feld" onchange="this.form.submit()"><option value="">Alles</option>@foreach (\App\Support\Filter::ZEITEN as $k => $l)<option value="{{ $k }}" @selected($filter->zeit === $k)>{{ $l }}</option>@endforeach</select></label>
        @if ($projekte->isNotEmpty())
            <label><span>Projekt</span><select name="projekt" class="feld" onchange="this.form.submit()"><option value="">Alle</option>@foreach ($projekte as $p)<option value="{{ $p->id }}" @selected($filter->projekt === (string) $p->id)>{{ $p->name }}</option>@endforeach<option value="ohne" @selected($filter->projekt === 'ohne')>ohne Projekt</option></select></label>
        @endif
        @if ($kurse)
            <label><span>Kurs</span><select name="kurs" class="feld" onchange="this.form.submit()"><option value="">Alle</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected($filter->kurs === (int) $id)>{{ $t }}</option>@endforeach</select></label>
        @endif
        @unless ($ohneStatus)
            <label><span>Status</span><select name="st" class="feld" onchange="this.form.submit()"><option value="">Alles</option>@foreach ($status as $k => $l)<option value="{{ $k }}" @selected($filter->st === $k)>{{ $l }}</option>@endforeach</select></label>
        @endunless
        <label class="suchfeld"><span>Suche</span><span class="flex gap-1"><input type="search" name="q" value="{{ $filter->q }}" class="feld" placeholder="{{ $platzhalter }}"><button type="submit" class="knopf knopf-leise" aria-label="Suchen"><i class="fa-solid fa-magnifying-glass"></i></button></span></label>
    </form>
</details>

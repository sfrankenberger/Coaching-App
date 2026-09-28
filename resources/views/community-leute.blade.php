<x-layouts.app title="Teilnehmerinnen">
    <p class="m-0 mb-2"><a href="{{ route('community', array_filter(['k' => $kurs?->id])) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Community</a></p>
    <h1 class="mb-3">Teilnehmerinnen</h1>

    @if ($programme->count() > 1)
        <div class="pillen">
            <a href="{{ route('community.leute') }}" @class(['pille', 'an' => ! $kurs])>Alle anzeigen</a>
            @foreach ($programme as $p)
                <a href="{{ route('community.leute', ['k' => $p->id]) }}" @class(['pille', 'an' => $kurs?->id === $p->id])>{{ $p->title }}</a>
            @endforeach
        </div>
    @endif

    @foreach ($leute as $l)
        <div class="karte flex items-start gap-3">
            <span class="dossier-avatar" style="width:52px;height:52px">{{ mb_strtoupper(mb_substr($l['user']->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1">
                <b class="t">{{ $l['coach'] ? $l['user']->vorname() : $l['user']->name }}@if ($l['coach']) <span class="badge badge-rec">Coach</span>@endif @if ($l['ich']) <span class="badge badge-rec">du</span>@endif</b>
                @if ($l['ueber'])<span class="lesetext block text-md mt-1 whitespace-pre-line">{{ $l['ueber'] }}</span>@endif
                @if ($l['kurse']->isNotEmpty() && ! $kurs)<span class="hinweis block mt-1">{{ $l['kurse']->join(' · ') }}</span>@endif
                @if ($l['coach'] && ! $l['ich'])<a href="{{ route('gespraech.index') }}" class="hinweis block mt-1 no-underline"><i class="fa-regular fa-envelope"></i> Schreiben</a>@endif
            </span>
        </div>
    @endforeach

    <p class="hinweis mt-3">
        @if ($sichtbar)
            Du bist hier sichtbar. <a href="{{ route('profil') }}#community">Dein eigenes Profil bearbeiten</a>
        @else
            Du bist hier noch nicht sichtbar. Wenn du magst, zeig den anderen deinen Namen und ein paar Worte zu dir: <a href="{{ route('profil') }}#community">im Profil einschalten</a>.
        @endif
    </p>
</x-layouts.app>

<x-layouts.app title="Ansehen als">
    <h1 class="mb-1">Ansehen als</h1>
    <p class="unterzeile m-0 mb-3.5">Du siehst die App genau so wie diese Person, mit ihren Kursen, Nachrichten und Einstellungen. Alles, was du dabei tust, geschieht in ihrem Namen.</p>

    @if ($als)
        <div class="karte flex items-center gap-3">
            <span class="min-w-0 flex-1"><b class="t">Gerade als {{ $als->name }}</b><span class="hinweis block">{{ $als->email }}</span></span>
            <form method="post" action="{{ route('als.ende') }}">@csrf @method('DELETE')<button type="submit" class="knopf knopf-klein">Zurück zu {{ $echt->vorname() }}</button></form>
        </div>
    @endif

    <form method="get" class="suche m-0 mb-3">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="{{ $q }}" placeholder="Name oder E-Mail" aria-label="Suchen">
    </form>

    @foreach (['Team' => $team, 'Teilnehmerinnen' => $personen] as $titel => $liste)
        @if ($liste->isNotEmpty())
            <h2 class="abschnitt"><i class="fa-solid fa-{{ $titel === 'Team' ? 'briefcase' : 'people-group' }}"></i>{{ $titel }}<em>{{ $liste->count() }}</em></h2>
            @foreach ($liste as $m)
                <form method="post" action="{{ route('als.start', $m->user) }}" class="zeile" style="cursor:pointer" onclick="this.submit()">
                    @csrf
                    <x-avatar :user="$m->user" :size="44" class="ic" />
                    <span class="tx"><b>{{ $m->user->name }}@if ($m->user_id === $echt->id) <span class="badge badge-rec">du</span>@endif</b><span>{{ $m->user->email }} · {{ $m->role->label() }}{{ $m->last_seen_at ? ' · zuletzt '.\App\Support\Zeit::relativ($m->last_seen_at) : '' }}</span></span>
                    <button type="submit" class="knopf knopf-leise knopf-klein" style="flex:none">Ansehen</button>
                </form>
            @endforeach
        @endif
    @endforeach
</x-layouts.app>

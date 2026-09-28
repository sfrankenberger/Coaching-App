<x-layouts.app :title="$art->title" :schmal="true">
    <h1 class="m-0 mb-1">{{ $art->title }}</h1>
    <p class="unterzeile m-0 mb-4">{{ $art->duration }} Minuten{{ $art->text ? ' · '.$art->text : '' }}</p>

    @if ($tage->isEmpty())
        <x-leer icon="calendar-xmark">Gerade ist keine Zeit frei. Schau bald wieder rein.</x-leer>
    @else
        <form method="post" action="{{ route('buchen.gast.store', $art) }}" class="buchen">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            <div class="karte">
                <label class="block mb-3"><span class="feld-label">Dein Name</span><input type="text" name="name" class="feld" required maxlength="120" value="{{ old('name') }}" autocomplete="name"></label>
                <label class="block mb-3"><span class="feld-label">Deine E-Mail</span><input type="email" name="email" class="feld" required maxlength="190" value="{{ old('email') }}" autocomplete="email"></label>
                @error('email')<p class="fehler mb-2">{{ $message }}</p>@enderror
                <label class="block"><span class="feld-label">Handynummer, freiwillig</span><input type="tel" name="phone" class="feld" maxlength="40" value="{{ old('phone') }}" autocomplete="tel"></label>
            </div>

            <h2 class="abschnitt"><i class="fa-regular fa-clock"></i>Wann passt es dir?</h2>
            @foreach ($tage as $tag => $zeiten)
                <details class="karte" @if ($loop->first) open @endif>
                    <summary class="t cursor-pointer">{{ $zeiten->first()->translatedFormat('l, j. F') }} <span class="m" style="display:inline">· {{ $zeiten->count() }} {{ $zeiten->count() === 1 ? 'Zeit' : 'Zeiten' }}</span></summary>
                    <div class="flex flex-wrap gap-2 mt-2.5">
                        @foreach ($zeiten as $z)
                            <label class="zeit-wahl">
                                <input type="radio" name="start" value="{{ $z->getTimestamp() }}" required>
                                <span>{{ $z->format('H:i') }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach
            @error('start')<p class="fehler">{{ $message }}</p>@enderror

            @if (! empty($art->questions))
                <h2 class="abschnitt"><i class="fa-solid fa-feather"></i>Magst du mir vorher etwas mitgeben?</h2>
                <div class="karte">
                    @foreach ($art->questions as $i => $frage)
                        <label class="block mb-3">
                            <span class="feld-label">{{ $frage }}</span>
                            <textarea name="antworten[{{ $i }}]" rows="3" class="feld" maxlength="3000" data-ohne-diktat>{{ old('antworten.'.$i) }}</textarea>
                        </label>
                    @endforeach
                    <p class="hinweis m-0">Freiwillig. Das liest nur deine Coachin.</p>
                </div>
            @endif

            <button type="submit" class="knopf knopf-gross" style="width:100%;margin-top:14px"><i class="fa-solid fa-calendar-check"></i>Verbindlich buchen</button>
            <p class="hinweis mt-2">Du bekommst eine Bestätigung per Mail mit dem Zugang zur App. Kein Passwort nötig.</p>
        </form>
    @endif
</x-layouts.app>

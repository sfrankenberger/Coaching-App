@props(['title' => null, 'schmal' => false, 'body' => null])
@inject('branding', App\Tenancy\Branding::class)
@php
    $appName = $branding->appName();
    $person = auth()->user();
    $tenant = $branding->tenant();
    $kannVerwalten = $person?->canManageCurrentTenant();
    $ungelesen = $person ? app(App\Chat\Chat::class)->unreadFor($person) : 0;
    $mitteilungen = $person ? $person->notifications()->whereNull('read_at')->count() : 0;
    $neuZahl = $person && ! $kannVerwalten ? app(App\Support\Besuche::class)->zaehler($person) : [];
    $avatar = $branding->get('avatar_url');
    $zusatz = $branding->get('mark_suffix');
    $links = (array) data_get($tenant?->settings, 'links', []);
    $kontakt = data_get($tenant?->settings, 'links.kontakt') ?? data_get($tenant?->settings, 'mail.from_address');

    // Drei Sichten: Teilnehmerin (einfach), Arbeitsplatz (Team im Alltag), Verwaltung (Filament unter /coach)
    $arbeitsplatz = App\Coach\Ansicht::arbeitsplatz($person);
    // Ansehen als: der echte Admin hinter der Verkleidung
    $alsEcht = App\Http\Middleware\AlsAndere::echt();
    $kannAls = $alsEcht?->is_platform_admin || $person?->is_platform_admin;
    // Kurse als Unterpunkte im Menue (nur fuer Teilnehmerinnen, fuer das Team wird es zu lang)
    $meineKurse = collect();
    if ($person && ! $kannVerwalten) {
        $meineKurse = App\Models\Program::query()
            ->whereIn('id', app(App\Programs\ProgramAccess::class)->programIdsFor($person))
            ->where('is_published', true)->whereNotIn('type', ['one_on_one', 'workbook'])->orderBy('position')->orderBy('title')
            ->get(['id', 'slug', 'title', 'icon', 'color']);
    }
    $ist = fn ($muster) => request()->routeIs($muster);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $branding->barColor() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $branding->shortName() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' | '.$appName : $appName }}</title>
    <link rel="manifest" href="{{ route('manifest') }}">
    @if ($icon = $branding->get('icon_url'))
        <link rel="icon" href="{{ $icon }}">
        <link rel="apple-touch-icon" href="{{ collect($branding->get('icons'))->firstWhere('sizes', '180x180')['src'] ?? $icon }}">
    @endif
    @if ($fontUrl = $branding->get('font_url'))
        @if (str_starts_with($fontUrl, 'https://fonts.googleapis.com'))
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        @endif
        <link rel="stylesheet" href="{{ $fontUrl }}">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/fa.min.css') }}">
    <style>{!! $branding->cssVariables() !!}</style>
    @if (config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key'))
        <meta name="reverb" content="{{ json_encode(['key' => config('broadcasting.connections.reverb.key'), 'host' => config('reverb.public.host') ?: request()->getHost(), 'port' => (int) config('reverb.public.port', 443), 'scheme' => config('reverb.public.scheme', 'https')]) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body @class([$body, 'ohne-leiste' => $person && ! $arbeitsplatz, 'mit-als' => (bool) $alsEcht, 'modus-team' => $kannVerwalten && $arbeitsplatz, 'modus-coachee' => $kannVerwalten && ! $arbeitsplatz])>
    <div class="oben">
    @if ($alsEcht)
        <div class="als-balken">
            <span><i class="fa-solid fa-eye"></i> Du siehst die App als <b>{{ $person->name }}</b> ({{ $person->roleIn()?->label() }})</span>
            <a href="{{ route('als') }}">Wechseln</a>
            <form method="post" action="{{ route('als.ende') }}">@csrf @method('DELETE')<button type="submit">Zurück zu {{ $alsEcht->vorname() }}</button></form>
        </div>
    @endif
    <header class="kopf">
        <div class="kopf-innen">
            @auth
                <button type="button" class="burger" aria-label="Menü öffnen" aria-expanded="false" aria-controls="menue" data-menue-auf><span></span><span></span><span></span></button>
            @endauth
            <a href="{{ route('home') }}" @class(['marke', 'ohne-zusatz' => ! $zusatz])>
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="" width="32" height="32">
                @elseif ($logo = $branding->get('logo_url'))
                    <img src="{{ $logo }}" alt="" class="logo">
                @endif
                <span class="n">{{ $appName }}</span>
                @if ($zusatz)<span class="z">{{ $zusatz }}</span>@endif
            </a>
            @if ($kannVerwalten)
                {{-- Team sieht auf einen Blick, ob es gerade als Team oder wie eine Teilnehmerin unterwegs ist; Tipp schaltet um --}}
                <form method="post" action="{{ route('ansicht') }}" class="modus-form">
                    @csrf<input type="hidden" name="ansicht" value="{{ $arbeitsplatz ? 'teilnehmer' : 'arbeitsplatz' }}">
                    <button type="submit" class="modus" title="{{ $arbeitsplatz ? 'Du arbeitest als Team. Tippen: wie eine Teilnehmerin' : 'Du siehst die App wie eine Teilnehmerin. Tippen: zurück zum Team' }}"><i class="fa-solid fa-{{ $arbeitsplatz ? 'briefcase' : 'eye' }}"></i>{{ $arbeitsplatz ? 'Team' : 'Als Teilnehmerin' }}</button>
                </form>
            @endif
            @auth
                @if ($arbeitsplatz)
                    <div class="kopf-rechts">
                        @if ($kannVerwalten)<a href="/coach" aria-label="Verwaltung" title="Verwaltung"><i class="fa-solid fa-sliders"></i></a>@endif
                        <a href="{{ route('mitteilungen') }}" aria-label="Mitteilungen"><i class="fa-{{ $mitteilungen ? 'solid' : 'regular' }} fa-bell"></i>@if ($mitteilungen)<span class="zahl">{{ $mitteilungen }}</span>@endif</a>
                        <a href="{{ route('gespraech.index') }}" aria-label="Gespräch"><i class="fa-solid fa-comments"></i>@if ($ungelesen)<span class="zahl">{{ $ungelesen }}</span>@endif</a>
                    </div>
                @elseif ($mitteilungen)
                    <div class="kopf-rechts">
                        <a href="{{ route('mitteilungen') }}" aria-label="Mitteilungen"><i class="fa-solid fa-bell"></i><span class="zahl">{{ $mitteilungen }}</span></a>
                    </div>
                @else
                    <span class="kopf-platz"></span>
                @endif
            @else
                <span class="kopf-platz"></span>
            @endauth
        </div>
    </header>
    </div>

    @auth
        <div class="pull" aria-hidden="true"><i class="fa-solid fa-rotate-right"></i></div>

        <aside class="drawer" id="menue" aria-hidden="true" aria-label="Menü">
            <div class="drawer-kopf">
                <span>Hallo {{ $person->vorname() }}@if ($kannVerwalten) <span class="modus modus-klein">{{ $arbeitsplatz ? 'Team' : 'Als Teilnehmerin' }}</span>@endif</span>
                <button type="button" class="drawer-zu" aria-label="Menü schliessen" data-menue-zu>&times;</button>
            </div>
            <nav>
                @if ($arbeitsplatz)
                    <ul>
                        @if ($kannAls)
                            <li><a href="{{ route('als') }}" @class(['aktiv' => $ist('als')])><i class="fa-solid fa-eye"></i>Ansehen als ...</a></li>
                        @endif
                        <li><a href="{{ route('home') }}" @class(['aktiv' => $ist('home')])><i class="fa-solid fa-sun"></i>Heute</a></li>
                        <li><a href="{{ route('coachees.index') }}" @class(['aktiv' => $ist('coachees.*')])><i class="fa-solid fa-people-group"></i>Coachees</a></li>
                        <li><a href="{{ route('gespraech.index') }}" @class(['aktiv' => $ist('gespraech.*')])><i class="fa-solid fa-comments"></i>Gespräche @if ($ungelesen)<span class="zahl">{{ $ungelesen }}</span>@endif</a></li>
                        <li><a href="{{ route('termine.index') }}" @class(['aktiv' => $ist('termine.*')])><i class="fa-solid fa-calendar"></i>Termine @if ($neuZahl['termine'] ?? 0)<span class="zahl">{{ $neuZahl['termine'] }}</span>@endif</a></li>
                        <li><a href="{{ route('nachschlagen.index') }}" @class(['aktiv' => $ist(['nachschlagen.*', 'themen.*', 'merkliste', 'werkzeuge.*', 'suche'])])><i class="fa-solid fa-magnifying-glass"></i>Nachschlagen</a></li>
                        <li><a href="{{ route('assistent') }}" @class(['aktiv' => $ist('assistent')])><i class="fa-solid fa-wand-magic-sparkles"></i>Assistent</a></li>
                        <li><a href="{{ route('kurse.index') }}" @class(['aktiv' => $ist('kurse.*')])><i class="fa-solid fa-graduation-cap"></i>Kurse</a></li>
                        <li><a href="/coach"><i class="fa-solid fa-sliders"></i>Verwaltung</a></li>
                        <li class="unter"><a href="/coach/programs"><i class="fa-solid fa-layer-group"></i>Kurse einrichten</a></li>
                        <li class="unter"><a href="/coach/events"><i class="fa-solid fa-calendar-plus"></i>Termine planen</a></li>
                        <li class="unter"><a href="/coach/offers"><i class="fa-solid fa-tags"></i>Angebote</a></li>
                        <li class="unter"><a href="/coach/einstellungen"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
                        <li><a href="{{ route('profil') }}" @class(['aktiv' => $ist('profil*')])><i class="fa-solid fa-user"></i>Profil</a></li>
                        <li>
                            <form method="post" action="{{ route('ansicht') }}">
                                @csrf<input type="hidden" name="ansicht" value="teilnehmer">
                                <button type="submit"><i class="fa-solid fa-eye"></i>Wie eine Teilnehmerin</button>
                            </form>
                        </li>
                        <li>
                            <form method="post" action="{{ route('abmelden') }}">
                                @csrf
                                <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i>Abmelden</button>
                            </form>
                        </li>
                    </ul>
                @else
                    <ul>
                        @if ($kannAls)
                            <li><a href="{{ route('als') }}" @class(['aktiv' => $ist('als')])><i class="fa-solid fa-eye"></i>Ansehen als ...</a></li>
                        @endif
                        @if ($kannVerwalten)
                            <li>
                                <form method="post" action="{{ route('ansicht') }}">
                                    @csrf<input type="hidden" name="ansicht" value="arbeitsplatz">
                                    <button type="submit"><i class="fa-solid fa-briefcase"></i>Zurück zum Arbeitsplatz</button>
                                </form>
                            </li>
                        @endif
                        @if ($kannVerwalten || $person?->is_platform_admin)
                            <li><a href="/coach"><i class="fa-solid fa-sliders"></i>Verwaltung</a></li>
                        @endif
                        <li><a href="{{ route('home') }}" @class(['aktiv' => $ist('home')])><i class="fa-solid fa-house"></i>Übersicht</a></li>
                        <li><a href="{{ route('gespraech.index') }}" @class(['aktiv' => $ist('gespraech.*')])><i class="fa-solid fa-user-group"></i>1:1 Coaching mit {{ $branding->coachName() }} @if ($ungelesen)<span class="zahl">{{ $ungelesen }}</span>@endif</a></li>
                        <li><a href="{{ route('termine.index') }}" @class(['aktiv' => $ist('termine.*')])><i class="fa-solid fa-calendar"></i>Termine @if ($neuZahl['termine'] ?? 0)<span class="zahl">{{ $neuZahl['termine'] }}</span>@endif</a></li>
                        <li><a href="{{ route('nachschlagen.index') }}" @class(['aktiv' => $ist(['nachschlagen.*', 'themen.*', 'merkliste', 'werkzeuge.*', 'suche'])])><i class="fa-solid fa-magnifying-glass"></i>Nachschlagen</a></li>
                        <li class="gruppe"><span><i class="fa-solid fa-book-open"></i>Meine Sachen</span></li>
                        <li class="unter"><a href="{{ route('aufgaben.index') }}" @class(['aktiv' => $ist('aufgaben.*')])><i class="fa-solid fa-list-check"></i>Meine Aufgaben</a></li>
                        <li class="unter"><a href="{{ route('notizen.index') }}" @class(['aktiv' => $ist('notizen.*')])><i class="fa-solid fa-note-sticky"></i>Meine Notizen</a></li>
                        <li class="unter"><a href="{{ route('reflexion.index') }}" @class(['aktiv' => $ist('reflexion.*')])><i class="fa-solid fa-pen-to-square"></i>Meine Reflexionen</a></li>
                        @if (App\Support\Funktionen::an('projekte'))<li class="unter"><a href="{{ route('projekte.index') }}" @class(['aktiv' => $ist('projekte.*')])><i class="fa-solid fa-lightbulb"></i>Meine Projekte</a></li>@endif
                        @if (App\Support\Funktionen::an('zeitleiste'))<li class="unter"><a href="{{ route('journal.index') }}" @class(['aktiv' => $ist('journal.*')])><i class="fa-solid fa-timeline"></i>Meine Zeitleiste</a></li>@endif
                        @if ($meineKurse->isNotEmpty())
                            <li class="gruppe"><span><i class="fa-solid fa-graduation-cap"></i>Meine Kurse</span></li>
                            @foreach ($meineKurse as $k)
                                <li class="unter"><a href="{{ route('kurse.show', $k) }}"><i class="fa-solid fa-{{ $k->icon ?: 'circle-play' }}"></i>{{ $k->title }}</a></li>
                            @endforeach
                        @else
                            <li><a href="{{ route('kurse.index') }}" @class(['aktiv' => $ist('kurse.*')])><i class="fa-solid fa-graduation-cap"></i>Meine Kurse</a></li>
                        @endif
                        <li><a href="{{ route('material.index') }}" @class(['aktiv' => $ist('material.*')])><i class="fa-solid fa-folder-open"></i>Ressourcen @if ($neuZahl['material'] ?? 0)<span class="zahl">{{ $neuZahl['material'] }}</span>@endif</a></li>
                        @if ($meineKurse->isNotEmpty() || $kannVerwalten)
                            <li><a href="{{ route('community') }}" @class(['aktiv' => $ist(['community', 'kurse.fragen', 'fragen.*'])])><i class="fa-solid fa-comments"></i>Community @if ($neuZahl['community'] ?? 0)<span class="zahl">{{ $neuZahl['community'] }}</span>@endif</a></li>
                        @endif
                        <li><a href="{{ route('impulse.index') }}" @class(['aktiv' => $ist('impulse.*')])><i class="fa-solid fa-lightbulb"></i>Impulse @if ($neuZahl['impulse'] ?? 0)<span class="zahl">{{ $neuZahl['impulse'] }}</span>@endif</a></li>
                        <li class="gruppe"><span><i class="fa-solid fa-user"></i>Mein Profil</span></li>
                        <li class="unter"><a href="{{ route('profil') }}"><i class="fa-solid fa-user"></i>Meine Daten</a></li>
                        <li class="unter"><a href="{{ route('profil') }}#buchungen"><i class="fa-solid fa-bookmark"></i>Meine Buchungen</a></li>
                        <li class="unter"><a href="{{ route('profil') }}#benachrichtigungen"><i class="fa-solid fa-bell"></i>Benachrichtigungen</a></li>
                        <li><a href="{{ route('hilfe') }}" @class(['aktiv' => $ist('hilfe')])><i class="fa-solid fa-life-ring"></i>Hilfe</a></li>
                        <li>
                            <form method="post" action="{{ route('abmelden') }}">
                                @csrf
                                <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i>Abmelden</button>
                            </form>
                        </li>
                    </ul>
                @endif
            </nav>
            @if ($links || $kontakt)
                <p class="drawer-fuss">
                    @if (! empty($links['impressum']))<a href="{{ $links['impressum'] }}" target="_blank" rel="noopener">Impressum</a>@endif
                    @if (! empty($links['impressum']) && ! empty($links['datenschutz'])) · @endif
                    @if (! empty($links['datenschutz']))<a href="{{ $links['datenschutz'] }}" target="_blank" rel="noopener">Datenschutz</a>@endif
                    @if ($kontakt)<br><a href="mailto:{{ $kontakt }}">{{ $kontakt }}</a>@endif
                    @if (! empty($links['website']))<br><a href="{{ $links['website'] }}" target="_blank" rel="noopener">Zur Website</a>@endif
                </p>
            @endif
        </aside>
        <div class="schleier-menue" data-menue-zu></div>
    @endauth

    <main @class(['seite', 'inhalt', 'seite-schmal' => $schmal])>
        @if (session('meldung'))
            <div class="meldung meldung-gut mb-3">{{ session('meldung') }}</div>
        @endif
        @if (session('fehler'))
            <div class="meldung meldung-schlecht mb-3">{{ session('fehler') }}</div>
        @endif
        {{ $slot }}
    </main>

    @auth
        @unless (request()->routeIs('gespraech.*'))
            <a href="{{ route('gespraech.index') }}" class="chat-knopf" aria-label="Gespräch">
                <i class="fa-solid fa-comment-dots"></i>
                @if ($ungelesen)<span class="zahl">{{ $ungelesen }}</span>@endif
            </a>
        @endunless
        @if ($arbeitsplatz)
            <nav class="leiste" aria-label="Navigation unten">
                <a href="{{ route('home') }}" @class(['aktiv' => $ist('home')])><i class="fa-solid fa-sun"></i>Heute</a>
                <a href="{{ route('coachees.index') }}" @class(['aktiv' => $ist('coachees.*')])><i class="fa-solid fa-people-group"></i>Coachees</a>
                <a href="{{ route('gespraech.index') }}" @class(['aktiv' => $ist('gespraech.*')])><i class="fa-solid fa-comments"></i>Gespräche</a>
                <a href="{{ route('termine.index') }}" @class(['aktiv' => $ist('termine.*')])><i class="fa-solid fa-calendar"></i>Termine</a>
                <a href="{{ route('assistent') }}" @class(['aktiv' => $ist(['assistent', 'nachschlagen.*'])])><i class="fa-solid fa-wand-magic-sparkles"></i>Assistent</a>
            </nav>
        @endif

        {{-- Hinweis-Fenster: Installieren und Push, einmal pro Tag hoechstens --}}
        <div class="sheet" id="app-sheet" aria-hidden="true" data-coach="{{ app(\App\Tenancy\Branding::class)->coachName() }}" data-push-schluessel="{{ route('push.schluessel') }}" data-push-abo="{{ route('push.abo') }}" data-start="{{ $ist('home') ? 1 : 0 }}">
            <div class="sheet-schleier" data-sheet-zu></div>
            <div class="sheet-in" role="dialog" aria-modal="true">
                <button type="button" class="sheet-zu" aria-label="Schliessen" data-sheet-zu>&times;</button>
                <div data-sheet-inhalt></div>
            </div>
        </div>
    @endauth

    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        }
    </script>
    @stack('scripts')
</body>
</html>

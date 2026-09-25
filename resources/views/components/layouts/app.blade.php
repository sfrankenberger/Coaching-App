@props(['title' => null, 'schmal' => false, 'body' => null])
@inject('branding', App\Tenancy\Branding::class)
@php
    $appName = $branding->appName();
    $person = auth()->user();
    $tenant = $branding->tenant();
    $kannVerwalten = $person?->canManageCurrentTenant();
    $ungelesen = $person ? app(App\Chat\Chat::class)->unreadFor($person) : 0;
    $mitteilungen = $person ? $person->notifications()->whereNull('read_at')->count() : 0;
    $avatar = $branding->get('avatar_url');
    $zusatz = $branding->get('mark_suffix');
    $links = (array) data_get($tenant?->settings, 'links', []);
    $kontakt = data_get($tenant?->settings, 'links.kontakt') ?? data_get($tenant?->settings, 'mail.from_address');

    // Kurse als Unterpunkte im Menue (fuer Verwaltende nur der Sammelpunkt, sonst wird es zu lang)
    $meineKurse = collect();
    if ($person && ! $kannVerwalten) {
        $meineKurse = App\Models\Program::query()
            ->whereIn('id', app(App\Programs\ProgramAccess::class)->programIdsFor($person))
            ->where('is_published', true)->orderBy('position')->orderBy('title')
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
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body @if ($body) class="{{ $body }}" @endif>
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
            @auth
                <div class="kopf-rechts">
                    <a href="{{ route('mitteilungen') }}" aria-label="Mitteilungen"><i class="fa-{{ $mitteilungen ? 'solid' : 'regular' }} fa-bell"></i>@if ($mitteilungen)<span class="zahl">{{ $mitteilungen }}</span>@endif</a>
                    <a href="{{ route('gespraech.index') }}" aria-label="Gespräch"><i class="fa-solid fa-comments"></i>@if ($ungelesen)<span class="zahl">{{ $ungelesen }}</span>@endif</a>
                </div>
            @else
                <span class="kopf-platz"></span>
            @endauth
        </div>
    </header>

    @auth
        <div class="pull" aria-hidden="true"><i class="fa-solid fa-rotate-right"></i></div>

        <aside class="drawer" id="menue" aria-hidden="true" aria-label="Menü">
            <div class="drawer-kopf">
                <span>Hallo {{ $person->vorname() }}</span>
                <button type="button" class="drawer-zu" aria-label="Menü schliessen" data-menue-zu>&times;</button>
            </div>
            <nav>
                <ul>
                    <li><a href="{{ route('home') }}" @class(['aktiv' => $ist('home')])><i class="fa-solid fa-house"></i>Start</a></li>
                    <li><a href="{{ route('kurse.index') }}" @class(['aktiv' => $ist('kurse.index')])><i class="fa-solid fa-graduation-cap"></i>{{ $kannVerwalten ? 'Alle Kurse' : 'Meine Kurse' }}</a></li>
                    @foreach ($meineKurse as $k)
                        <li class="unter"><a href="{{ route('kurse.show', $k) }}"><i class="fa-solid fa-{{ $k->icon ?: 'circle-play' }}"></i>{{ $k->title }}</a></li>
                    @endforeach
                    <li><a href="{{ route('termine.index') }}" @class(['aktiv' => $ist('termine.*')])><i class="fa-solid fa-calendar"></i>Termine</a></li>
                    <li><a href="{{ route('material.index') }}" @class(['aktiv' => $ist('material.*')])><i class="fa-solid fa-folder-open"></i>Material</a></li>
                    <li><a href="{{ route('impulse.index') }}" @class(['aktiv' => $ist('impulse.*')])><i class="fa-solid fa-lightbulb"></i>Impulse</a></li>
                    <li class="unter"><a href="{{ route('themen.index') }}"><i class="fa-solid fa-tags"></i>Nachschlagen</a></li>
                    <li class="unter"><a href="{{ route('suche') }}"><i class="fa-solid fa-magnifying-glass"></i>Suchen</a></li>
                    <li class="unter"><a href="{{ route('merkliste') }}"><i class="fa-solid fa-bookmark"></i>Gemerkt</a></li>
                    <li><a href="{{ route('journal.index') }}" @class(['aktiv' => $ist('journal.*')])><i class="fa-solid fa-book-open"></i>Mein Journal</a></li>
                    <li class="unter"><a href="{{ route('aufgaben.index') }}"><i class="fa-solid fa-list-check"></i>Aufgaben</a></li>
                    <li class="unter"><a href="{{ route('notizen.index') }}"><i class="fa-solid fa-note-sticky"></i>Notizen</a></li>
                    <li class="unter"><a href="{{ route('reflexion.index') }}"><i class="fa-solid fa-pen-to-square"></i>Reflexion</a></li>
                    <li><a href="{{ route('gespraech.index') }}" @class(['aktiv' => $ist('gespraech.*')])><i class="fa-solid fa-comments"></i>Gespräch @if ($ungelesen)<span class="zahl">{{ $ungelesen }}</span>@endif</a></li>
                    <li><a href="{{ route('mitteilungen') }}" @class(['aktiv' => $ist('mitteilungen*')])><i class="fa-solid fa-bell"></i>Mitteilungen @if ($mitteilungen)<span class="zahl">{{ $mitteilungen }}</span>@endif</a></li>
                    <li><a href="{{ route('profil') }}" @class(['aktiv' => $ist('profil*')])><i class="fa-solid fa-user"></i>Profil</a></li>
                    @if ($kannVerwalten)
                        <li><a href="/coach"><i class="fa-solid fa-user-group"></i>Coach-Bereich</a></li>
                    @endif
                    <li><a href="{{ route('profil') }}#hilfe"><i class="fa-solid fa-life-ring"></i>Hilfe</a></li>
                    <li>
                        <form method="post" action="{{ route('abmelden') }}">
                            @csrf
                            <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i>Abmelden</button>
                        </form>
                    </li>
                </ul>
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
        <nav class="leiste" aria-label="Navigation unten">
            <a href="{{ route('home') }}" @class(['aktiv' => $ist('home')])><i class="fa-solid fa-house"></i>Start</a>
            <a href="{{ route('kurse.index') }}" @class(['aktiv' => $ist('kurse.*')])><i class="fa-solid fa-graduation-cap"></i>Kurse</a>
            <a href="{{ route('termine.index') }}" @class(['aktiv' => $ist('termine.*')])><i class="fa-solid fa-calendar"></i>Termine</a>
            <a href="{{ route('journal.index') }}" @class(['aktiv' => $ist(['journal.*', 'aufgaben.*', 'notizen.*', 'reflexion.*'])])><i class="fa-solid fa-book-open"></i>Journal</a>
            <a href="{{ route('impulse.index') }}" @class(['aktiv' => $ist(['impulse.*', 'themen.*', 'merkliste'])])><i class="fa-solid fa-lightbulb"></i>Impulse</a>
        </nav>

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

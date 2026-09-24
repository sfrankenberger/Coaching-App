@inject('branding', App\Tenancy\Branding::class)
@php
    $appName = $branding->appName();
    $person = auth()->user();
    $kannVerwalten = $person?->canManageCurrentTenant();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $branding->get('card_bg') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $branding->shortName() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' | '.$appName : $appName }}</title>
    <link rel="manifest" href="{{ route('manifest') }}">
    @if ($icon = $branding->get('icon_url'))
        <link rel="icon" href="{{ $icon }}">
        <link rel="apple-touch-icon" href="{{ $icon }}">
    @endif
    @if ($fontUrl = $branding->get('font_url'))
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $fontUrl }}">
    @endif
    <style>{!! $branding->cssVariables() !!}</style>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
    <header class="kopf">
        <div class="seite kopf-innen">
            <a href="{{ route('home') }}" class="marke">
                @if ($logo = $branding->get('logo_url'))
                    <img src="{{ $logo }}" alt="{{ $appName }}">
                @else
                    {{ $appName }}
                @endif
            </a>
            @auth
                <nav class="nav" aria-label="Hauptnavigation">
                    <a href="{{ route('home') }}" @class(['aktiv' => request()->routeIs('home')])>Start</a>
                    <a href="{{ route('kurse.index') }}" @class(['aktiv' => request()->routeIs('kurse.*')])>Kurse</a>
                    <a href="{{ route('termine.index') }}" @class(['aktiv' => request()->routeIs('termine.*')])>Termine</a>
                    <a href="{{ route('material.index') }}" @class(['aktiv' => request()->routeIs('material.*')])>Material</a>
                    <a href="{{ route('journal.index') }}" @class(['aktiv' => request()->routeIs(['journal.*', 'aufgaben.*', 'notizen.*', 'reflexion.*'])])>Journal</a>
                    <a href="{{ route('profil') }}" @class(['aktiv' => request()->routeIs('profil*')])>Profil</a>
                    @if ($kannVerwalten)
                        <a href="/coach">Coach-Bereich</a>
                    @endif
                    <form method="post" action="{{ route('abmelden') }}">
                        @csrf
                        <button type="submit" class="knopf knopf-leise" style="min-height:36px;padding:6px 14px">Abmelden</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="seite inhalt">
        @if (session('meldung'))
            <div class="meldung meldung-gut" style="margin-bottom:var(--gap)">{{ session('meldung') }}</div>
        @endif
        @if (session('fehler'))
            <div class="meldung meldung-schlecht" style="margin-bottom:var(--gap)">{{ session('fehler') }}</div>
        @endif
        {{ $slot }}
    </main>

    @auth
        <nav class="leiste" aria-label="Navigation unten">
            <a href="{{ route('home') }}" @class(['aktiv' => request()->routeIs('home')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 3l9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>
                Start
            </a>
            <a href="{{ route('kurse.index') }}" @class(['aktiv' => request()->routeIs('kurse.*')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 8l10-4 10 4-10 4z"/><path d="M6 10v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/></svg>
                Kurse
            </a>
            <a href="{{ route('termine.index') }}" @class(['aktiv' => request()->routeIs('termine.*')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                Termine
            </a>
            <a href="{{ route('journal.index') }}" @class(['aktiv' => request()->routeIs(['journal.*', 'aufgaben.*', 'notizen.*', 'reflexion.*'])])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M8 8h7M8 12h7"/></svg>
                Journal
            </a>
            <a href="{{ route('profil') }}" @class(['aktiv' => request()->routeIs('profil*')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Profil
            </a>
        </nav>
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

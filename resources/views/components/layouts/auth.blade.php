@inject('branding', App\Tenancy\Branding::class)
@php $appName = $branding->appName(); @endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $branding->get('card_bg') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $branding->shortName() }}">
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
</head>
<body class="min-h-dvh grid place-items-center">
    <main class="w-full max-w-[420px] px-4 py-8">
        <div class="text-center mb-6">
            @if ($logo = $branding->get('logo_url'))
                <img src="{{ $logo }}" alt="{{ $appName }}" class="mx-auto h-12 w-auto">
            @else
                <span class="font-heading text-2xl">{{ $appName }}</span>
            @endif
        </div>
        @if (session('meldung'))
            <div class="meldung meldung-gut mb-3">{{ session('meldung') }}</div>
        @endif
        @if (session('fehler'))
            <div class="meldung meldung-schlecht mb-3">{{ session('fehler') }}</div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>

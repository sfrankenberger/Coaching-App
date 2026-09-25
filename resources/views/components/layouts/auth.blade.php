@inject('branding', App\Tenancy\Branding::class)
@php $appName = $branding->appName(); @endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $branding->barColor() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $branding->shortName() }}">
    <title>{{ isset($title) ? $title.' | '.$appName : $appName }}</title>
    <link rel="manifest" href="{{ route('manifest') }}">
    @if ($icon = $branding->get('icon_url'))
        <link rel="icon" href="{{ $icon }}">
        <link rel="apple-touch-icon" href="{{ $icon }}">
    @endif
    @if ($fontUrl = $branding->get('font_url'))
        <link rel="stylesheet" href="{{ $fontUrl }}">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/fa.min.css') }}">
    <style>{!! $branding->cssVariables() !!}</style>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="min-h-dvh grid place-items-center">
    <main class="w-full max-w-[420px] px-4 py-8">
        <div class="text-center" style="margin:0 0 22px">
            @if ($avatar = $branding->get('avatar_url'))
                <img src="{{ $avatar }}" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block">
            @elseif ($logo = $branding->get('logo_url'))
                <img src="{{ $logo }}" alt="{{ $appName }}" class="mx-auto h-12 w-auto mb-3">
            @else
                <span style="width:80px;height:80px;border-radius:50%;background:var(--c-neutral);color:var(--c-primary);display:grid;place-items:center;margin:0 auto 12px;font-size:28px"><i class="fa-solid fa-key"></i></span>
            @endif
            <span class="block" style="font-family:var(--font-mark);font-size:15px;letter-spacing:.14em">{{ $appName }}</span>
            @if ($zusatz = $branding->get('mark_suffix'))<span class="block" style="font-family:var(--font-mark);font-size:14px;letter-spacing:.14em;color:var(--c-muted)">{{ $zusatz }}</span>@endif
        </div>
        @if (session('meldung'))
            <div class="meldung meldung-gut mb-3">{{ session('meldung') }}</div>
        @endif
        @if (session('fehler'))
            <div class="meldung meldung-schlecht mb-3">{{ session('fehler') }}</div>
        @endif
        {{ $slot }}
    </main>
    @stack('scripts')
</body>
</html>

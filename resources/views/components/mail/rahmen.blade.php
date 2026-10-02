@props(['profil' => false, 'fuss' => null, 'newsletter' => false])
@inject('branding', App\Tenancy\Branding::class)
@php
    $tenant = $branding->tenant();
    $logo = $branding->get('logo_url');
    $logo = $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : null;
    $fusszeile = $tenant?->setting('mail.fusszeile');
    $social = $newsletter ? array_filter((array) ($tenant?->setting('newsletter.social') ?? [])) : [];
    $socialNamen = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'website' => 'Website', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];
    $knopf = 'display:inline-block;background:'.$branding->get('primary').';color:'.$branding->get('primary_contrast').';text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;';
    $knopfLeise = 'display:inline-block;border:1px solid '.$branding->get('primary').';color:'.$branding->get('primary').';text-decoration:none;font-weight:600;font-size:16px;padding:12px 26px;border-radius:999px;';
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding->appName() }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:{{ $branding->get('bg') }};font-family:{{ $branding->get('font_body') }};color:{{ $branding->get('text') }};">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;">
        <tr><td style="padding:0 0 16px;text-align:center;font-size:20px;font-family:{{ $branding->get('font_heading') }};">
            @if ($logo)
                <a href="{{ url('/') }}" style="text-decoration:none;color:{{ $branding->get('text') }};"><img src="{{ $logo }}" alt="{{ $branding->appName() }}" height="44" style="height:44px;max-width:220px;border:0;vertical-align:middle;"></a>
            @else
                <a href="{{ url('/') }}" style="text-decoration:none;color:{{ $branding->get('text') }};">{{ $branding->appName() }}</a>
            @endif
        </td></tr>
        <tr><td style="background:{{ $branding->get('card_bg') }};border:1px solid {{ $branding->get('card_border') }};border-radius:{{ (int) $branding->get('radius') }}px;padding:24px;">
            {{ $slot }}
        </td></tr>
        <tr><td style="padding:16px 8px 0;font-size:12px;line-height:1.6;color:{{ $branding->get('muted') }};text-align:center;">
            @if ($social)<div style="margin:0 0 12px;">@foreach ($social as $name => $url)<a href="{{ $url }}" title="{{ $socialNamen[$name] ?? ucfirst($name) }}" style="display:inline-block;margin:0 8px;text-decoration:none;color:{{ $branding->get('text') }};font-weight:600;">@if (file_exists(public_path('img/social/'.$name.'.png')))<img src="{{ url('/img/social/'.$name.'.png') }}" width="22" height="22" alt="{{ $socialNamen[$name] ?? ucfirst($name) }}" style="display:inline-block;width:22px;height:22px;border:0;vertical-align:middle;">@else{{ $socialNamen[$name] ?? ucfirst($name) }}@endif</a>@endforeach</div>@endif
            @if ($fuss){{ $fuss }}<br>@endif
            @php $coach = $branding->coachName(); @endphp
            {{ $fusszeile ? (mb_stripos($fusszeile, $coach) !== false ? $fusszeile : $coach.' · '.$fusszeile) : $coach }}
            @if ($profil)<br><a href="{{ route('profil') }}#benachrichtigungen" style="color:{{ $branding->get('muted') }};">Was dich erreicht, stellst du in deinem Profil ein.</a>@endif
        </td></tr>
    </table>
</body>
</html>

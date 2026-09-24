<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:{{ $branding->get('bg') }};font-family:{{ $branding->get('font_body') }};color:{{ $branding->get('text') }};">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;">
        <tr><td style="padding:0 0 16px;text-align:center;font-size:20px;font-family:{{ $branding->get('font_heading') }};">{{ $appName }}</td></tr>
        <tr><td style="background:{{ $branding->get('card_bg') }};border:1px solid {{ $branding->get('card_border') }};border-radius:{{ (int) $branding->get('radius') }}px;padding:24px;">
            <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hallo {{ $user->vorname() }}</p>
            <p style="margin:0 0 8px;font-size:17px;font-weight:600;line-height:1.4;">{{ $nachricht->titel }}</p>
            <div style="margin:0 0 20px;font-size:16px;line-height:1.55;white-space:pre-line;">{!! nl2br(e($nachricht->text)) !!}</div>
            @if ($nachricht->url)
                <p style="margin:0 0 8px;text-align:center;">
                    <a href="{{ $nachricht->url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">{{ $nachricht->knopf ?: 'Ansehen' }}</a>
                </p>
            @endif
        </td></tr>
        <tr><td style="padding:16px 8px 0;font-size:12px;line-height:1.5;color:{{ $branding->get('muted') }};text-align:center;">Was dich erreicht, stellst du in deinem Profil ein.</td></tr>
    </table>
</body>
</html>

@inject('branding', App\Tenancy\Branding::class)
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding->appName() }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:{{ $branding->get('bg') }};font-family:{{ $branding->get('font_body') }};color:{{ $branding->get('text') }};">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;">
        <tr><td style="padding:0 0 16px;text-align:center;font-size:20px;font-family:{{ $branding->get('font_heading') }};">{{ $branding->appName() }}</td></tr>
        <tr><td style="background:{{ $branding->get('card_bg') }};border:1px solid {{ $branding->get('card_border') }};border-radius:{{ (int) $branding->get('radius') }}px;padding:24px;">
            <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hallo {{ $user->vorname() }}</p>
            <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Schön, dass du dabei bist. {{ $angebot ? $angebot.' ist für dich freigeschaltet.' : 'Dein Zugang ist da.' }}</p>
            <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Mit dem Knopf bist du direkt angemeldet. Danach kannst du dir jederzeit einen neuen Link an diese Adresse schicken lassen, ein Passwort brauchst du nicht.</p>
            <p style="margin:0 0 20px;text-align:center;">
                <a href="{{ $url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">Jetzt loslegen</a>
            </p>
            <p style="margin:0;font-size:13px;line-height:1.5;color:{{ $branding->get('muted') }};">Falls der Knopf nicht geht, kopiere diese Adresse in den Browser:<br><a href="{{ $url }}" style="color:{{ $branding->get('primary') }};word-break:break-all;">{{ $url }}</a></p>
        </td></tr>
        <tr><td style="padding:16px 8px 0;font-size:12px;line-height:1.5;color:{{ $branding->get('muted') }};text-align:center;">Der Link gilt sieben Tage. Tipp: Auf dem Handy die App zum Startbildschirm hinzufügen, dann ist sie immer griffbereit.</td></tr>
    </table>
</body>
</html>

<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ $tenant->branding['app_name'] ?? $tenant->name }}</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#F6F2EA;color:#2B2724}
        .karte{max-width:420px;margin:24px;padding:28px 24px;background:{{ $tenant->branding['card_bg'] ?? '#fff' }};border:1px solid {{ $tenant->branding['card_border'] ?? '#ddd' }};border-radius:{{ $tenant->branding['radius'] ?? 16 }}px;text-align:center}
        h1{font-size:26px;margin:0 0 8px} p{font-size:15.5px;line-height:1.5;margin:0;color:#6b635c}
    </style>
</head>
<body>
    <div class="karte">
        <h1>{{ $tenant->branding['app_name'] ?? $tenant->name }}</h1>
        <p>Die neue App entsteht hier. Bald geht es los.</p>
    </div>
</body>
</html>

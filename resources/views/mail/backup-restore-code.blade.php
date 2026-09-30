<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f5f5f4;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#1c1917;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;">
        <tr><td style="padding:0 0 16px;text-align:center;font-size:18px;">{{ $appName }}: Datensicherung</td></tr>
        <tr><td style="background:#ffffff;border:1px solid #e7e5e4;border-radius:12px;padding:24px;">
            <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hallo {{ $user->vorname() }}</p>
            <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Du willst einen gesicherten Stand wiederherstellen. Dein Code dafür:</p>
            <p style="margin:0 0 20px;text-align:center;font-size:32px;letter-spacing:8px;font-weight:700;">{{ $code }}</p>
            <p style="margin:0;font-size:13px;line-height:1.5;color:#78716c;">Der Code gilt {{ $minuten }} Minuten und nur für diesen Vorgang. Wenn du das nicht warst, stelle nichts wieder her und ändere dein Passwort.</p>
        </td></tr>
    </table>
</body>
</html>

@inject('branding', App\Tenancy\Branding::class)
<x-mail.rahmen>
    <x-slot:fuss>Du hast das nicht angefordert? Dann ignoriere diese Mail, es ändert sich nichts.</x-slot:fuss>
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hallo {{ $k->vorname() }}</p>
    <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Ein Klick, und du bekommst keine Post mehr von {{ $branding->coachName() }}. Schade, aber alles gut.</p>
    <p style="margin:0 0 20px;text-align:center;">
        <a href="{{ $url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">Abmelden</a>
    </p>
    <p style="margin:0;font-size:13px;line-height:1.5;color:{{ $branding->get('muted') }};">Falls der Knopf nicht geht, kopiere diese Adresse in den Browser:<br><a href="{{ $url }}" style="color:{{ $branding->get('primary') }};word-break:break-all;">{{ $url }}</a></p>
</x-mail.rahmen>

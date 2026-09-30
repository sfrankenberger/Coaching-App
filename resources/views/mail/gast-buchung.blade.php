@inject('branding', App\Tenancy\Branding::class)
<x-mail.rahmen>
    <x-slot:fuss>Der Link gilt sieben Tage, danach schickst du dir auf der Anmeldeseite einfach einen neuen an diese Adresse. Tipp: Auf dem Handy die App zum Startbildschirm hinzufügen.</x-slot:fuss>

    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hallo {{ $user->vorname() }}</p>
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Schön, dass du dir die Zeit nimmst. Dein Termin steht:</p>
    <p style="margin:0 0 12px;font-size:18px;line-height:1.5;font-family:{{ $branding->get('font_heading') }};">{{ $booking->type?->title ?? 'Gespräch' }}<br>{{ \App\Support\Zeit::wann($booking->starts_at) }} Uhr</p>
    @if ($booking->event?->zoom_url)
        <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Wir treffen uns online: <a href="{{ $booking->event->zoom_url }}" style="color:{{ $branding->get('primary') }};">{{ $booking->event->zoom_url }}</a></p>
    @endif
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">{{ $neu ? 'Ich habe dir dazu einen Platz in meiner App eingerichtet.' : 'Den Termin findest du auch in der App.' }} Dort siehst du den Termin, kannst ihn verschieben oder absagen, findest Impulse und den Podcast, und kannst mir direkt schreiben. Schau dich gern um, das ist alles für dich.</p>
    <p style="margin:0 0 20px;text-align:center;">
        <a href="{{ $url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">Zur App und zum Termin</a>
    </p>
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Bis bald,<br>{{ $branding->coachName() }}</p>
    <p style="margin:0;font-size:13px;line-height:1.5;color:{{ $branding->get('muted') }};">Falls der Knopf nicht geht, kopiere diese Adresse in den Browser:<br><a href="{{ $url }}" style="color:{{ $branding->get('primary') }};word-break:break-all;">{{ $url }}</a></p>
</x-mail.rahmen>

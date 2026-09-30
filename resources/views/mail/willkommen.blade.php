@inject('branding', App\Tenancy\Branding::class)
<x-mail.rahmen>
    <x-slot:fuss>Der Link gilt sieben Tage. Tipp: Auf dem Handy die App zum Startbildschirm hinzufügen, dann ist sie immer griffbereit.</x-slot:fuss>
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hallo {{ $user->vorname() }}</p>
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Schön, dass du dabei bist. {{ $angebot ? $angebot.' ist für dich freigeschaltet.' : 'Dein Zugang ist da.' }}</p>
    @if ($text)
        <div style="margin:0 0 12px;font-size:16px;line-height:1.55;white-space:pre-line;">{!! nl2br(e($text)) !!}</div>
    @elseif ($art === 'one_on_one')
        <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">In der App findest du unser Gespräch, deine Termine und alles, was wir miteinander teilen. Schreib mir dort, wann immer dir etwas durch den Kopf geht.</p>
    @elseif ($art)
        <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">In der App findest du die Inhalte Schritt für Schritt, dazu die Gruppe und den Austausch. Nimm dir Zeit, es läuft dir nichts davon.</p>
    @endif
    <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Mit dem Knopf bist du direkt angemeldet. Eine kurze Einführung zeigt dir danach, wo alles liegt. Später kannst du dir jederzeit einen neuen Link an diese Adresse schicken lassen, ein Passwort brauchst du nicht.</p>
    <p style="margin:0 0 20px;text-align:center;">
        <a href="{{ $url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">Jetzt loslegen</a>
    </p>
    <p style="margin:0;font-size:13px;line-height:1.5;color:{{ $branding->get('muted') }};">Falls der Knopf nicht geht, kopiere diese Adresse in den Browser:<br><a href="{{ $url }}" style="color:{{ $branding->get('primary') }};word-break:break-all;">{{ $url }}</a></p>
</x-mail.rahmen>

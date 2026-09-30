@inject('branding', App\Tenancy\Branding::class)
@php $offen = $v->zahlungsart === 'rechnung'; @endphp
<x-mail.rahmen>

    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Liebe {{ $user->vorname() }}</p>
    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">
        @if ($offen)
            Schön, dass du dabei bist. Hier ist deine Rechnung für <strong>{{ $v->title }}</strong>: {{ $v->betragText() }}{{ $v->faellig_am ? ', zahlbar bis '.$v->faellig_am->translatedFormat('j. F Y') : '' }}.
        @else
            Herzlichen Dank. Die Zahlung für <strong>{{ $v->title }}</strong> ({{ $v->betragText() }}) ist eingegangen{{ $mitPdf ? ', die Quittung hängt an dieser Mail' : '' }}.
        @endif
    </p>
    @if ($offen && $mitPdf)
        <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Die Rechnung mit allen Angaben hängt als PDF an dieser Mail.</p>
    @endif
    @if ($offen && $v->rechnung_link)
        <p style="margin:0 0 20px;text-align:center;">
            <a href="{{ $v->rechnung_link }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">Online bezahlen</a>
        </p>
    @endif
    @if ($url)
        <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">{{ $v->settings['warten_auf_zahlung'] ?? false ? 'Sobald die Zahlung da ist, ist dein Zugang offen.' : 'Dein Zugang ist offen. Mit dem Knopf bist du direkt angemeldet, ein Passwort brauchst du nicht.' }}</p>
        <p style="margin:0 0 20px;text-align:center;">
            <a href="{{ $url }}" style="display:inline-block;border:1px solid {{ $branding->get('primary') }};color:{{ $branding->get('primary') }};text-decoration:none;font-weight:600;font-size:16px;padding:12px 26px;border-radius:999px;">Zur App</a>
        </p>
    @endif
    <p style="margin:0;font-size:13px;line-height:1.5;color:{{ $branding->get('muted') }};">Bei Fragen antworte einfach auf diese Mail.</p>
</x-mail.rahmen>

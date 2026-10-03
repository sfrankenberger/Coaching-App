<x-mail.rahmen :profil="true">
    <h1 style="margin:0 0 18px;font-size:24px;line-height:1.3;font-weight:400;font-family:{{ $branding->get('font_heading') }};">{{ $nachricht->titel }}</h1>
    <p style="margin:0 0 14px;font-size:17px;line-height:1.6;">Hallo {{ $user->vorname() }}</p>
    @if ($nachricht->bloecke)
        {!! \App\Newsletter\Bausteine::html($nachricht->bloecke, (new \App\Models\Kontakt)->forceFill(['name' => $user->name, 'email' => $user->email])) !!}
    @else
        <div style="margin:0 0 20px;font-size:17px;line-height:1.6;">{!! nl2br(e($nachricht->text)) !!}</div>
    @endif
    @if ($nachricht->html)
        <div style="margin:0 0 20px;padding-left:14px;border-left:3px solid {{ $branding->get('card_border') }};font-size:16px;line-height:1.6;">{{ \App\Support\Kapitel::sauber($nachricht->html) }}</div>
    @endif
    @foreach ($nachricht->liste ?? [] as $eintrag)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 10px;"><tr><td style="border:1px solid {{ $branding->get('card_border') }};border-radius:10px;padding:12px 14px;">
            @if (! empty($eintrag['herkunft']))<span style="display:block;font-size:12px;color:{{ $branding->get('muted') }};margin:0 0 2px;">{{ $eintrag['herkunft'] }}</span>@endif
            <span style="display:block;font-size:15px;font-weight:600;line-height:1.4;">{{ $eintrag['titel'] }}</span>
            @if (! empty($eintrag['text']))<span style="display:block;font-size:14px;line-height:1.45;margin:2px 0 0;">{{ $eintrag['text'] }}</span>@endif
            @if (! empty($eintrag['url']))<a href="{{ $eintrag['url'] }}" style="display:inline-block;margin:8px 0 0;font-size:14px;font-weight:600;color:{{ $branding->get('primary') }};text-decoration:none;">{{ $eintrag['knopf'] ?? 'Ansehen und antworten' }} &rarr;</a>@endif
        </td></tr></table>
    @endforeach
    @if ($nachricht->url)
        <p style="margin:{{ $nachricht->liste ? '16px' : '0' }} 0 8px;text-align:center;">
            <a href="{{ $nachricht->url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">{{ $nachricht->knopf ?: 'Ansehen' }}</a>
        </p>
    @endif
</x-mail.rahmen>

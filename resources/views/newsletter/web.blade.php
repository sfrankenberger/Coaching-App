@inject('branding', App\Tenancy\Branding::class)
<x-mail.rahmen>
    <x-slot:fuss>@if ($k)<a href="{{ route('newsletter.abmelden', $k->token) }}" style="color:{{ $branding->get('muted') }};">Abmelden mit einem Klick</a>@endif</x-slot:fuss>
    @if ($n->bild_url)<img src="{{ $n->bild_url }}" alt="" width="472" style="width:100%;max-width:472px;height:auto;border-radius:12px;margin:0 0 18px;display:block;">@endif
    @if ($n->titel)<h1 style="margin:0 0 14px;font-size:24px;line-height:1.3;font-weight:400;font-family:{{ $branding->get('font_heading') }};">{{ \App\Newsletter\Vorlage::platzhalter($n->titel, $k) }}</h1>@endif
    {!! $html !!}
    @if ($n->knopf_text && $n->knopf_url)
        <p style="margin:6px 0 8px;text-align:center;"><a href="{{ $n->knopf_url }}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">{{ $n->knopf_text }}</a></p>
    @endif
</x-mail.rahmen>

@inject('branding', App\Tenancy\Branding::class)
@php $text = \App\Newsletter\Vorlage::platzhalter((string) $n->text, $k); @endphp
<x-mail.rahmen>
    <x-slot:fuss>@if ($k->exists)<a href="{{ route('newsletter.abmelden', $k->token) }}" style="color:{{ $branding->get('muted') }};">Abmelden mit einem Klick</a>@if ($v) · <a href="{{ route('newsletter.web', $v->token) }}" style="color:{{ $branding->get('muted') }};">Im Browser ansehen</a>@endif @endif</x-slot:fuss>
    @if ($n->vorschautext)<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ \App\Newsletter\Vorlage::platzhalter($n->vorschautext, $k) }}</div>@endif
    @if ($n->bild_url)<img src="{{ $n->bild_url }}" alt="" width="472" style="width:100%;max-width:472px;height:auto;border-radius:12px;margin:0 0 18px;display:block;">@endif
    @if ($n->titel)<h1 style="margin:0 0 14px;font-size:24px;line-height:1.3;font-weight:400;font-family:{{ $branding->get('font_heading') }};">{{ \App\Newsletter\Vorlage::platzhalter($n->titel, $k) }}</h1>@endif
    {!! \App\Newsletter\Vorlage::html($text, $v) !!}
    @if ($n->knopf_text && $n->knopf_url)
        <p style="margin:6px 0 8px;text-align:center;">
            <a href="{!! \App\Newsletter\Vorlage::link($n->knopf_url, $v) !!}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">{{ $n->knopf_text }}</a>
        </p>
    @endif
    @if ($v)<img src="{{ route('newsletter.oeffnen', $v->token) }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">@endif
</x-mail.rahmen>

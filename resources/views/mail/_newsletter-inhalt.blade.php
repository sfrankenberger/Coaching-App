@inject('branding', App\Tenancy\Branding::class)
{{-- Inhalt eines Newsletters oder einer Serienmail: Bausteine, sonst die alten Felder (Bild, Headline, Text, Knopf). $n Newsletter, $k Kontakt, $v Versand oder null --}}
@if (! empty($n->bloecke))
    {!! \App\Newsletter\Bausteine::html($n->bloecke, $k, $v) !!}
@else
    @if ($n->bild_url)<img src="{{ $n->bild_url }}" alt="" width="472" style="width:100%;max-width:472px;height:auto;border-radius:12px;margin:0 0 18px;display:block;">@endif
    @if ($n->titel)<h1 style="margin:0 0 14px;font-size:24px;line-height:1.3;font-weight:400;font-family:{{ $branding->get('font_heading') }};">{{ \App\Newsletter\Vorlage::platzhalter($n->titel, $k) }}</h1>@endif
    {!! \App\Newsletter\Vorlage::html(\App\Newsletter\Vorlage::platzhalter((string) $n->text, $k), $v) !!}
    @if ($n->knopf_text && $n->knopf_url)
        <p style="margin:6px 0 8px;text-align:center;">
            <a href="{!! \App\Newsletter\Vorlage::link($n->knopf_url, $v) !!}" style="display:inline-block;background:{{ $branding->get('primary') }};color:{{ $branding->get('primary_contrast') }};text-decoration:none;font-weight:600;font-size:16px;padding:13px 26px;border-radius:999px;">{{ $n->knopf_text }}</a>
        </p>
    @endif
@endif

@inject('branding', App\Tenancy\Branding::class)
<x-mail.rahmen :newsletter="true">
    <x-slot:fuss>@if ($k->exists)<a href="{{ route('newsletter.abmelden', $k->token) }}" style="color:{{ $branding->get('muted') }};">Abmelden mit einem Klick</a>@if ($v) · <a href="{{ route('newsletter.web', $v->token) }}" style="color:{{ $branding->get('muted') }};">Im Browser ansehen</a>@endif @endif</x-slot:fuss>
    @if ($n->vorschautext)<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ \App\Newsletter\Vorlage::platzhalter($n->vorschautext, $k) }}</div>@endif
    @include('mail._newsletter-inhalt', ['n' => $n, 'k' => $k, 'v' => $v])
    @if ($v)<img src="{{ route('newsletter.oeffnen', $v->token) }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">@endif
</x-mail.rahmen>

@inject('branding', App\Tenancy\Branding::class)
{{-- Vorschau im Coach-Bereich: so sieht die Mail bei einer Empfaengerin aus, ohne Zaehlung --}}
<x-mail.rahmen :newsletter="true">
    <x-slot:fuss><a href="#" style="color:{{ $branding->get('muted') }};">Abmelden mit einem Klick</a> · <a href="#" style="color:{{ $branding->get('muted') }};">Im Browser ansehen</a></x-slot:fuss>
    @include('mail._newsletter-inhalt', ['n' => $n, 'k' => $k, 'v' => null])
</x-mail.rahmen>

@inject('branding', App\Tenancy\Branding::class)
<x-mail.rahmen :newsletter="true">
    <x-slot:fuss>@if ($k)<a href="{{ route('newsletter.abmelden', $k->token) }}" style="color:{{ $branding->get('muted') }};">Abmelden mit einem Klick</a>@endif</x-slot:fuss>
    @include('mail._newsletter-inhalt', ['n' => $n, 'k' => $k, 'v' => null])
</x-mail.rahmen>

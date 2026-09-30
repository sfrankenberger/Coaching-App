<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\User;
use App\Models\Verkauf;
use App\Shop\Buchhaltung;
use App\Shop\Verkaufen;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Kasse der App: /kaufen/{angebot}. Wer angemeldet ist, kauft mit einem Klick; Gaeste geben Name und
 * Mail an, das Konto entsteht dabei. Heute: Kauf auf Rechnung (bexio). Karte und Twint folgen mit Stripe.
 */
class KaufenController extends Controller
{
    public function __construct(protected CurrentTenant $current, protected Verkaufen $verkaufen) {}

    public function show(Request $request, string $angebot): View
    {
        $offer = $this->angebot($angebot);
        $preise = $offer->preise();
        $waehrung = $this->waehrung($request, $preise);

        return view('kaufen.show', [
            'offer' => $offer,
            'preise' => $preise,
            'waehrung' => $waehrung,
            'preis' => $offer->is_free ? 0.0 : ($preise[$waehrung] ?? null),
            'regulaer' => $offer->aktionLaeuft() ? $offer->preisRegulaer($waehrung) : null,
            'ref' => Str::limit((string) $request->query('ref', ''), 80, ''),
            'rechnung' => $offer->kaufAufRechnung() && (($b = Buchhaltung::fuer($this->current->get())) && $b->kannSchreiben()),
            'person' => $request->user(),
            'hat' => $request->user()?->entitlements()->where('offer_id', $offer->id)->where('status', 'active')->exists() ?? false,
            'zugangSofort' => $this->current->get()?->setting('buchhaltung.zugang_bei_rechnung', 'sofort') !== 'bezahlt',
        ]);
    }

    public function store(Request $request, string $angebot): RedirectResponse
    {
        $offer = $this->angebot($angebot);
        $tenant = $this->current->getOrFail();
        $data = $request->validate([
            'name' => [$request->user() ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:190'],
            'waehrung' => ['nullable', 'in:CHF,EUR'],
            'zahlung' => ['required', 'in:rechnung,gratis'],
            'ref' => ['nullable', 'string', 'max:80'],
            'agb' => ['accepted'],
            'website' => ['nullable', 'size:0'],   // Honigtopf
        ]);
        $preise = $offer->preise();
        $waehrung = $this->waehrung($request, $preise, $data['waehrung'] ?? null);
        $betrag = $offer->is_free ? 0.0 : ($preise[$waehrung] ?? null);
        abort_if($betrag === null, 422, 'Für dieses Angebot gibt es keinen Preis in '.$waehrung.'.');
        if ($betrag > 0) {
            abort_unless($data['zahlung'] === 'rechnung' && $offer->kaufAufRechnung() && ($b = Buchhaltung::fuer($tenant)) && $b->kannSchreiben(), 422, 'Kauf auf Rechnung ist gerade nicht möglich.');
        }

        $user = $request->user() ?? $this->konto($data['name'], $data['email'], $tenant);
        $v = $this->verkaufen->verkaufen(null, $user, $offer, [
            'betrag' => $betrag, 'waehrung' => $waehrung, 'zahlungsart' => $betrag > 0 ? 'rechnung' : 'kostenlos',
            'rechnung' => $betrag > 0, 'mail' => true, 'herkunft' => 'kasse'.(filled($data['ref'] ?? null) ? ':'.trim($data['ref']) : ''),
        ]);
        $request->session()->put('kauf_'.$offer->id, $v->id);

        return redirect()->route('kaufen.danke', $offer->slug);
    }

    public function danke(Request $request, string $angebot): View
    {
        $offer = $this->angebot($angebot, nurKaufbar: false);
        $v = Verkauf::find((int) $request->session()->get('kauf_'.$offer->id));
        abort_unless($v, 404);

        return view('kaufen.danke', ['offer' => $offer, 'v' => $v, 'angemeldet' => (bool) $request->user()]);
    }

    protected function angebot(string $slug, bool $nurKaufbar = true): Offer
    {
        $offer = Offer::with('programs')->where('slug', $slug)->where('is_active', true)->first();
        abort_unless($offer && (! $nurKaufbar || $offer->kaufbar()), 404);

        return $offer;
    }

    protected function waehrung(Request $request, array $preise, ?string $gewuenscht = null): string
    {
        $w = strtoupper((string) ($gewuenscht ?: $request->query('w', $this->current->get()?->currency ?: 'CHF')));

        return isset($preise[$w]) || $preise === [] ? $w : array_key_first($preise);
    }

    /** Konto fuer eine neue Person: Nutzer und Mitgliedschaft, ohne Mail (die kommt mit der Rechnung). */
    protected function konto(string $name, string $email, $tenant): User
    {
        $email = Str::lower(trim($email));
        $user = User::where('email', $email)->first() ?? User::create(['name' => trim($name), 'email' => $email]);
        if (! $user->membershipIn($tenant)) {
            Membership::anlegen(['user_id' => $user->id, 'role' => Role::Member->value, 'status' => 'active', 'joined_at' => now(), 'settings' => ['quelle' => 'kasse']]);
        }

        return $user;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\User;
use App\Models\Verkauf;
use App\Shop\Buchhaltung;
use App\Shop\Stripe;
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

        $tenant = $this->current->get();

        return view('kaufen.show', [
            'offer' => $offer,
            'preise' => $preise,
            'waehrung' => $waehrung,
            'stripe' => app(Stripe::class)->konfiguriert(),
            'links' => (array) data_get($tenant?->settings, 'links', []),
            'adresse' => (array) ($request->user()?->membershipIn()?->setting('adresse') ?? []),
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
            'zahlung' => ['required', 'in:rechnung,gratis,stripe'],
            'ref' => ['nullable', 'string', 'max:80'],
            'agb' => ['accepted'],
            'widerruf' => [$offer->is_free ? 'nullable' : 'accepted'],
            'strasse' => [$offer->is_free ? 'nullable' : 'required', 'string', 'max:160'],
            'plz' => [$offer->is_free ? 'nullable' : 'required', 'string', 'max:12'],
            'ort' => [$offer->is_free ? 'nullable' : 'required', 'string', 'max:120'],
            'land' => ['nullable', 'string', 'max:2'],
            'website' => ['nullable', 'size:0'],   // Honigtopf
        ], ['widerruf.accepted' => 'Bitte bestätige den Verzicht auf das Widerrufsrecht, sonst können wir nicht sofort freischalten.']);
        $preise = $offer->preise();
        $waehrung = $this->waehrung($request, $preise, $data['waehrung'] ?? null);
        $betrag = $offer->is_free ? 0.0 : ($preise[$waehrung] ?? null);
        abort_if($betrag === null, 422, 'Für dieses Angebot gibt es keinen Preis in '.$waehrung.'.');
        $stripe = app(Stripe::class);
        $zahlung = $betrag > 0 ? $data['zahlung'] : 'gratis';
        if ($betrag > 0) {
            $rechnungGeht = $offer->kaufAufRechnung() && ($b = Buchhaltung::fuer($tenant)) && $b->kannSchreiben();
            abort_unless(($zahlung === 'rechnung' && $rechnungGeht) || ($zahlung === 'stripe' && $stripe->konfiguriert()), 422, 'Diese Zahlweise ist gerade nicht möglich.');
        }

        $user = $request->user() ?? $this->konto($data['name'], $data['email'], $tenant);
        // Rechtliches und Rechnungsadresse mit Zeitstempel am Verkauf, die Adresse auch an der Person
        $adresse = array_filter(['strasse' => trim((string) ($data['strasse'] ?? '')), 'plz' => trim((string) ($data['plz'] ?? '')), 'ort' => trim((string) ($data['ort'] ?? '')), 'land' => strtoupper(trim((string) ($data['land'] ?? 'CH')))]);
        if ($adresse && ($m = $user->membershipIn($tenant))) {
            $m->forceFill(['settings' => array_merge($m->settings ?? [], ['adresse' => $adresse])])->saveQuietly();
        }
        $recht = ['agb_at' => now()->toIso8601String(), 'widerruf_verzicht_at' => $betrag > 0 ? now()->toIso8601String() : null, 'ip' => $request->ip(), 'adresse' => $adresse];

        $v = $this->verkaufen->verkaufen(null, $user, $offer, [
            'betrag' => $betrag, 'waehrung' => $waehrung, 'zahlungsart' => $betrag > 0 ? $zahlung : 'kostenlos',
            'rechnung' => $betrag > 0, 'mail' => true, 'herkunft' => 'kasse'.(filled($data['ref'] ?? null) ? ':'.trim($data['ref']) : ''),
            'settings' => $recht,
        ]);
        $request->session()->put('kauf_'.$offer->id, $v->id);

        if ($zahlung === 'stripe') {
            try {
                $s = $stripe->checkout($v, route('kaufen.danke', $offer->slug).'?session_id={CHECKOUT_SESSION_ID}', route('kaufen', ['angebot' => $offer->slug, 'abbruch' => 1]));
            } catch (\Throwable $e) {
                report($e);
                $v->forceFill(['status' => 'storniert', 'settings' => array_merge($v->settings ?? [], ['stripe_fehler' => $e->getMessage()])])->save();

                return back()->withInput()->with('fehler', 'Die Kasse ist gerade nicht erreichbar. Versuch es gleich nochmal oder wähle Kauf auf Rechnung.');
            }
            $v->forceFill(['settings' => array_merge($v->settings ?? [], ['stripe_session_id' => $s['id']])])->save();

            return redirect()->away($s['url']);
        }

        return redirect()->route('kaufen.danke', $offer->slug);
    }

    public function danke(Request $request, string $angebot): View
    {
        $offer = $this->angebot($angebot, nurKaufbar: false);
        $v = Verkauf::find((int) $request->session()->get('kauf_'.$offer->id));
        abort_unless($v, 404);
        // Zurueck von Stripe: Zahlung sofort pruefen, falls der Webhook noch unterwegs ist
        if ($v->zahlungsart === 'stripe' && $v->status === 'offen' && ($sid = (string) $request->query('session_id')) !== '' && $sid === ($v->settings['stripe_session_id'] ?? null)) {
            try {
                $s = app(Stripe::class)->session($sid);
                if (($s['payment_status'] ?? null) === 'paid') {
                    $this->verkaufen->stripeBezahlt($v, $s);
                    $v->refresh();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

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
            Membership::create(['user_id' => $user->id, 'role' => Role::Member->value, 'status' => 'active', 'joined_at' => now(), 'settings' => ['quelle' => 'kasse']]);
        }

        return $user;
    }
}

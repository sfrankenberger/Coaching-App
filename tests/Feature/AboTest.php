<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\RechnungMail;
use App\Models\Entitlement;
use App\Models\Offer;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verkauf;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Abos ueber Stripe: Kasse im Abo-Modus, Verlaengerung, Zahlungsausfall, Kuendigung, Kundenportal, Umzug. */
class AboTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected Offer $club;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'currency' => 'CHF', 'settings' => ['coach_name' => 'Lea',
            'stripe' => ['secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_1'],
            'links' => ['agb' => 'https://lea.test/agb', 'widerruf' => 'https://lea.test/widerruf'],
            'buchhaltung' => ['anbieter' => 'bexio', 'bexio' => ['token' => 'fest-123', 'stammdaten' => ['waehrungen' => [1 => 'CHF', 2 => 'EUR']], 'schreiben' => ['user_id' => 3, 'account_id' => 90, 'frist' => 30]]],
        ]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->club = $this->in(function () {
            $p = Program::create(['slug' => 'club', 'title' => 'Club', 'type' => 'club', 'is_published' => true]);
            $o = Offer::create(['title' => 'Club-Abo', 'type' => 'club', 'is_active' => true, 'settings' => ['sichtbar' => true, 'preis_chf' => 49, 'abo_intervall' => 'monat', 'kauf_rechnung' => true]]);
            $o->programs()->attach($p, ['tenant_id' => $this->a->id]);

            return $o;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function fake(int $periodeEnde): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_abo_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_abo_1']),
            'api.stripe.com/v1/checkout/sessions/cs_abo_1' => Http::response(['id' => 'cs_abo_1', 'mode' => 'subscription', 'payment_status' => 'paid', 'subscription' => 'sub_1', 'customer' => 'cus_1']),
            'api.stripe.com/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'status' => 'active', 'current_period_end' => $periodeEnde]),
            'api.stripe.com/v1/subscriptions' => Http::response(['id' => 'sub_neu', 'status' => 'trialing', 'current_period_end' => $periodeEnde]),
            'api.stripe.com/v1/billing_portal/sessions' => Http::response(['url' => 'https://billing.stripe.com/p/session/1']),
            'api.bexio.com/2.0/contact/search*' => Http::response([]),
            'api.bexio.com/2.0/contact' => Http::response(['id' => 88]),
            'api.bexio.com/2.0/kb_invoice/701/*' => Http::response(['success' => true]),
            'api.bexio.com/2.0/kb_invoice/701' => Http::response(['id' => 701, 'document_nr' => 'RE-0701', 'network_link' => 'https://office.bexio.com/pay/701', 'kb_item_status_id' => 9]),
            'api.bexio.com/2.0/kb_invoice' => Http::response(['id' => 701]),
        ]);
    }

    protected function webhook(array $event): void
    {
        $payload = json_encode($event);
        $t = time();
        $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_1');
        $this->call('POST', 'http://a.test/hooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
    }

    public function test_abo_kaufen_verlaengern_ausfall_und_kuendigen(): void
    {
        $ende = now()->addMonth()->startOfSecond();
        $this->fake($ende->getTimestamp());

        $seite = $this->get('http://a.test/kaufen/club-abo')->assertOk();
        $seite->assertSee('monatlich, jederzeit kündbar')->assertSee('Das Abo verlängert sich monatlich von selbst')->assertDontSee('Auf Rechnung, zahlbar');
        $this->assertFalse($this->club->kaufAufRechnung(), 'Abos nie auf Rechnung');

        $this->post('http://a.test/kaufen/club-abo', ['name' => 'Nora Neu', 'email' => 'nora@test.ch', 'zahlung' => 'stripe', 'agb' => 1, 'widerruf' => 1, 'strasse' => 'Weg 1', 'plz' => '8000', 'ort' => 'Zürich'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_abo_1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/checkout/sessions') && $r['mode'] === 'subscription' && $r['line_items'][0]['price_data']['recurring']['interval'] === 'month' && $r['line_items'][0]['price_data']['unit_amount'] == 4900);
        $v = $this->in(fn () => Verkauf::first());
        $nora = User::where('email', 'nora@test.ch')->first();

        // Checkout fertig (Abo-Modus)
        $this->webhook(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_abo_1', 'mode' => 'subscription', 'payment_status' => 'paid', 'subscription' => 'sub_1', 'customer' => 'cus_1', 'client_reference_id' => (string) $v->id, 'metadata' => ['verkauf_id' => (string) $v->id, 'tenant_id' => (string) $this->a->id]]]]);
        $v = $v->fresh();
        $this->assertSame('bezahlt', $v->status);
        $this->assertSame('sub_1', $v->settings['stripe_subscription_id']);
        $e = $this->in(fn () => Entitlement::where('user_id', $nora->id)->first());
        $this->assertSame('stripe', $e->source);
        $this->assertSame('sub_1', $e->source_ref);
        $this->assertTrue($e->isCurrent());
        $this->assertSame($ende->copy()->addDays(3)->toDateTimeString(), $e->ends_at->utc()->toDateTimeString(), 'Periodenende plus Kulanz');
        $this->assertSame('cus_1', $this->in(fn () => $nora->membershipIn()->settings['stripe_customer_id']));
        Mail::assertSent(RechnungMail::class, fn ($m) => $m->hasTo('nora@test.ch'));

        // Profil: Abo verwalten fuehrt ins Kundenportal
        $nora->membershipIn($this->a)->forceFill(['settings' => array_merge($nora->membershipIn($this->a)->settings, ['onboarding_seen_at' => now()->toDateTimeString()])])->save();
        $this->actingAs($nora)->get('http://a.test/profil')->assertOk()->assertSee('Abo, monatlich')->assertSee('bezahlt bis '.$ende->translatedFormat('j. F Y'))->assertSee(route('abo.portal'));
        $this->actingAs($nora)->get('http://a.test/abo/portal')->assertRedirect('https://billing.stripe.com/p/session/1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/billing_portal/sessions') && $r['customer'] === 'cus_1');

        // Naechster Monat: Rechnung bezahlt, Zugang verlaengert, Quittung als neuer Verkauf
        $ende2 = $ende->copy()->addMonth();
        $this->webhook(['id' => 'evt_2', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_2', 'subscription' => 'sub_1', 'customer' => 'cus_1', 'billing_reason' => 'subscription_cycle', 'amount_paid' => 4900, 'currency' => 'chf', 'lines' => ['data' => [['period' => ['end' => $ende2->getTimestamp()]]]]]]]);
        $e = $e->fresh();
        $this->assertSame($ende2->copy()->addDays(3)->toDateTimeString(), $e->ends_at->utc()->toDateTimeString());
        $this->assertSame(2, $this->in(fn () => Verkauf::count()));
        $v2 = $this->in(fn () => Verkauf::latest('id')->first());
        $this->assertSame('Club-Abo (Verlängerung)', $v2->title);
        $this->assertSame('bezahlt', $v2->status);
        $this->assertSame('in_2', $v2->settings['stripe_invoice_id']);
        $this->assertSame('RE-0701', $v2->rechnung_nr);
        Mail::assertSentTimes(RechnungMail::class, 2);
        // Dieselbe Rechnung nochmal: nichts doppelt
        $this->webhook(['id' => 'evt_2b', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_2', 'subscription' => 'sub_1', 'billing_reason' => 'subscription_cycle', 'amount_paid' => 4900, 'currency' => 'chf']]]);
        $this->assertSame(2, $this->in(fn () => Verkauf::count()));
        // Erste Rechnung des Abos gehoert zur Kasse, kein zweiter Verkauf
        $this->webhook(['id' => 'evt_0', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_1', 'subscription' => 'sub_1', 'billing_reason' => 'subscription_create', 'amount_paid' => 4900, 'currency' => 'chf']]]);
        $this->assertSame(2, $this->in(fn () => Verkauf::count()));

        // Zahlung schlaegt fehl: Person und Team hoeren davon
        $this->webhook(['id' => 'evt_3', 'type' => 'invoice.payment_failed', 'data' => ['object' => ['id' => 'in_3', 'subscription' => 'sub_1']]]);
        Notification::assertSentTo($nora, AppNotification::class, fn ($n) => $n->nachricht->titel === 'Deine Abo-Zahlung hat nicht geklappt' && $n->nachricht->url === route('abo.portal'));
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Abo-Zahlung fehlgeschlagen'));

        // Gekuendigt zum Periodenende, dann beendet
        $this->webhook(['id' => 'evt_4', 'type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_1', 'status' => 'active', 'cancel_at_period_end' => true, 'current_period_end' => $ende2->getTimestamp()]]]);
        $this->assertTrue($this->in(fn () => Verkauf::latest('id')->first()->settings['abo_gekuendigt']));
        $this->webhook(['id' => 'evt_5', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_1', 'status' => 'canceled', 'current_period_end' => $ende2->getTimestamp()]]]);
        $e = $e->fresh();
        $this->assertSame('cancelled', $e->status);
        $this->assertSame($ende2->toDateTimeString(), $e->ends_at->utc()->toDateTimeString(), 'Zugang bis zum Periodenende');
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Abo beendet'));
    }

    public function test_bestehendes_abo_umziehen(): void
    {
        $ab = now()->addDays(10)->startOfDay();
        $this->fake($ab->copy()->addMonth()->getTimestamp());
        $alt = User::factory()->create(['name' => 'Alt Abo', 'email' => 'alt@test.ch']);
        $this->a->users()->attach($alt, ['role' => Role::Member->value, 'status' => 'active']);

        $this->artisan('abo:umziehen', ['tenant' => 'a', 'email' => 'alt@test.ch', 'angebot' => 'club-abo', '--kunde' => 'cus_alt', '--ab' => $ab->toDateString(), '--trocken' => true])
            ->expectsOutputToContain('"trocken": true')->assertSuccessful();
        $this->assertSame(0, $this->in(fn () => Verkauf::count()));

        $this->artisan('abo:umziehen', ['tenant' => 'a', 'email' => 'alt@test.ch', 'angebot' => 'club-abo', '--kunde' => 'cus_alt', '--ab' => $ab->toDateString()])
            ->expectsOutputToContain('"abo": "sub_neu"')->assertSuccessful();
        $erwartet = Carbon::parse($ab->toDateString(), 'Europe/Zurich')->getTimestamp();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/subscriptions') && $r['customer'] === 'cus_alt' && $r['proration_behavior'] === 'none' && (int) $r['trial_end'] === $erwartet);
        $e = $this->in(fn () => Entitlement::where('user_id', $alt->id)->first());
        $this->assertSame('stripe', $e->source);
        $this->assertSame('sub_neu', $e->source_ref);
        $this->assertTrue($e->isCurrent());
        $this->assertSame('cus_alt', $this->in(fn () => $alt->membershipIn()->settings['stripe_customer_id']));
        $this->assertTrue($this->in(fn () => $alt->fresh()->membershipIn()->user->id === $alt->id));
        $this->artisan('abo:umziehen', ['tenant' => 'a', 'email' => 'alt@test.ch', 'angebot' => 'club-abo', '--kunde' => 'falsch'])->assertFailed();
    }

    public function test_ohne_kundennummer_kein_portal(): void
    {
        $this->fake(now()->addMonth()->getTimestamp());
        $this->lea->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        $this->actingAs($this->lea)->get('http://a.test/abo/portal')->assertRedirect('http://a.test/profil#buchungen')->assertSessionHas('fehler');
        $this->assertSame('monat', $this->getJson('http://a.test/api/angebote')->json('angebote.0.abo'));
    }
}

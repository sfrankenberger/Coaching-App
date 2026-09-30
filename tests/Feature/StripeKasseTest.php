<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\RechnungMail;
use App\Models\Entitlement;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verkauf;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Kasse mit Stripe (Karte, Twint), Rechnung nachholen bei Stoerung, Warnung vor Ablauf, Preis im Dossier vorbefuellt. */
class StripeKasseTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected Offer $offer;

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
        $this->offer = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid', 'is_published' => true]);
            $o = Offer::create(['title' => 'Hybrid Herbst', 'type' => 'hybrid', 'access_days' => 180, 'is_active' => true, 'settings' => ['sichtbar' => true, 'preis_chf' => 990, 'preis_eur' => 1000]]);
            $o->programs()->attach($p, ['tenant_id' => $this->a->id]);

            return $o;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function bexioFake(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1']),
            'api.bexio.com/2.0/contact/search*' => Http::response([]),
            'api.bexio.com/2.0/contact' => Http::response(['id' => 88]),
            'api.bexio.com/2.0/kb_invoice/701/*' => Http::response(['success' => true]),
            'api.bexio.com/2.0/kb_invoice/701' => Http::response(['id' => 701, 'document_nr' => 'RE-0701', 'network_link' => 'https://office.bexio.com/pay/701', 'kb_item_status_id' => 9]),
            'api.bexio.com/2.0/kb_invoice' => Http::response(['id' => 701]),
        ]);
    }

    public function test_kauf_mit_stripe_webhook_schaltet_frei_und_schickt_quittung(): void
    {
        $this->bexioFake();
        $seite = $this->get('http://a.test/kaufen/hybrid-herbst')->assertOk();
        $seite->assertSee('Karte oder Twint, sofort')->assertSee('Auf Rechnung, zahlbar innert 30 Tagen')->assertSee('https://lea.test/agb')->assertSee('Widerrufsbelehrung')->assertSee('Rechnungsadresse');

        $this->post('http://a.test/kaufen/hybrid-herbst', ['name' => 'Nora Neu', 'email' => 'nora@test.ch', 'zahlung' => 'stripe', 'agb' => 1, 'widerruf' => 1, 'strasse' => 'Weg 1', 'plz' => '8000', 'ort' => 'Zürich', 'ref' => 'insta'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/checkout/sessions') && $r['line_items'][0]['price_data']['unit_amount'] == 99000 && $r['line_items'][0]['price_data']['currency'] === 'chf' && $r['customer_email'] === 'nora@test.ch');
        $v = $this->in(fn () => Verkauf::first());
        $nora = User::where('email', 'nora@test.ch')->first();
        $this->assertSame('stripe', $v->zahlungsart);
        $this->assertSame('offen', $v->status);
        $this->assertSame('cs_test_1', $v->settings['stripe_session_id']);
        $this->assertSame('pending', $this->in(fn () => Entitlement::where('user_id', $nora->id)->first()->status), 'Zugang erst mit der Zahlung');
        Mail::assertNothingSent();

        // Webhook: Zahlung da
        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'client_reference_id' => (string) $v->id, 'metadata' => ['verkauf_id' => (string) $v->id, 'tenant_id' => (string) $this->a->id]]]]);
        $t = time();
        $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_1');
        $this->call('POST', 'http://a.test/hooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

        $v = $v->fresh();
        $this->assertSame('bezahlt', $v->status);
        $this->assertSame('pi_1', $v->settings['stripe_payment_intent']);
        $this->assertSame('RE-0701', $v->rechnung_nr, 'Quittung in bexio');
        $this->assertTrue($this->in(fn () => Entitlement::where('user_id', $nora->id)->first()->isCurrent()));
        Mail::assertSent(RechnungMail::class, fn ($m) => $m->hasTo('nora@test.ch') && str_contains($m->envelope()->subject, 'Quittung'));
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Bezahlt über Stripe'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/2.0/kb_invoice/701/issue') || str_contains($r->url(), '/kb_invoice/701/payment'));

        // Nochmal derselbe Webhook: nichts doppelt
        $this->call('POST', 'http://a.test/hooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        Mail::assertSentTimes(RechnungMail::class, 1);
    }

    public function test_danke_seite_prueft_die_zahlung_selbst_wenn_der_webhook_spaeter_kommt(): void
    {
        $this->bexioFake();
        $this->post('http://a.test/kaufen/hybrid-herbst', ['name' => 'Nora Neu', 'email' => 'nora@test.ch', 'zahlung' => 'stripe', 'agb' => 1, 'widerruf' => 1, 'strasse' => 'Weg 1', 'plz' => '8000', 'ort' => 'Zürich'])->assertRedirect();
        $this->get('http://a.test/kaufen/hybrid-herbst/danke?session_id=cs_test_1')->assertOk()->assertSee('Deine Zahlung über')->assertSee('freigeschaltet');
        $this->assertSame('bezahlt', $this->in(fn () => Verkauf::first()->status));
        Mail::assertSent(RechnungMail::class);
        // Abbruch bei Stripe: die Kasse zeigt es an
        $this->get('http://a.test/kaufen/hybrid-herbst?abbruch=1')->assertOk()->assertSee('Die Zahlung wurde abgebrochen');
    }

    public function test_rechnung_wird_nachgeholt_und_team_gewarnt_und_ablauf_gemeldet(): void
    {
        // bexio faellt zuerst aus und kommt spaeter wieder (Stubs stapeln sich, darum ein Schalter)
        $aus = true;
        Http::fake(function ($r) use (&$aus) {
            if ($aus) {
                return Http::response(['message' => 'aus'], 500);
            }
            $u = $r->url();

            return match (true) {
                str_contains($u, '/2.0/contact/search') => Http::response([]),
                str_ends_with($u, '/2.0/contact') => Http::response(['id' => 88]),
                str_ends_with($u, '/2.0/kb_invoice') => Http::response(['id' => 701]),
                str_ends_with($u, '/2.0/kb_invoice/701') => Http::response(['id' => 701, 'document_nr' => 'RE-0701', 'network_link' => 'https://office.bexio.com/pay/701', 'kb_item_status_id' => 8]),
                default => Http::response(['success' => true]),
            };
        });
        $anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@test.ch']);
        $this->a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->actingAs($anna)->post('http://a.test/kaufen/hybrid-herbst', ['zahlung' => 'rechnung', 'agb' => 1, 'widerruf' => 1, 'strasse' => 'Weg 1', 'plz' => '8000', 'ort' => 'Zürich'])->assertRedirect();
        $v = $this->in(fn () => Verkauf::first());
        $this->assertNull($v->rechnung_id);
        $this->assertNotEmpty($v->settings['rechnung_fehler']);

        // Zwei weitere Fehlversuche, beim dritten die Warnung ans Team
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->assertSuccessful();
        Notification::assertNotSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Rechnung nicht angelegt'));
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->assertSuccessful();
        $this->assertSame(3, $v->fresh()->settings['rechnung_versuche']);
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Rechnung nicht angelegt'));

        // bexio ist wieder da: Rechnung nachgeholt
        $aus = false;
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->expectsOutputToContain('nachgeholt')->assertSuccessful();
        $this->assertSame('RE-0701', $v->fresh()->rechnung_nr);
        $this->assertArrayNotHasKey('rechnung_fehler', $v->fresh()->settings);

        // Sieben Tage vor Ablauf: Hinweis an Anna und ans Team, einmal
        $this->in(fn () => Entitlement::where('user_id', $anna->id)->update(['ends_at' => now()->addDays(6)->addHours(12)]));
        Notification::fake();
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->expectsOutputToContain('läuft am')->assertSuccessful();
        Notification::assertSentTo($anna, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Dein Zugang läuft am'));
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Zugang läuft ab'));
        Notification::fake();
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->assertSuccessful();
        Notification::assertNotSentTo($anna, AppNotification::class);
    }

    public function test_dossier_verkauf_mit_vorbefuelltem_preis_und_waehrung_der_person(): void
    {
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);
        $m = $this->in(fn () => Membership::where('user_id', $anna->id)->first());
        $m->forceFill(['settings' => ['waehrung' => 'EUR']])->save();
        $this->actingAs($this->lea)->get("http://a.test/coachees/{$m->id}")->assertOk()
            ->assertSee('data-preise=', false)->assertSee('990.00 CHF / 1&#039;000.00 EUR', false)->assertSee('<option value="EUR" selected', false)->assertSee('Ohne Preis wird der Zugang kostenlos');
    }
}

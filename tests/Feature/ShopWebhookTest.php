<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\WillkommenMail;
use App\Models\Entitlement;
use App\Models\Offer;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookLog;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShopWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Program $kurs;

    protected Program $club;

    protected Offer $kursAngebot;

    protected Offer $clubAngebot;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['shop' => ['webhook_secret' => 'geheim']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'settings' => ['shop' => ['webhook_secret' => 'anders']]]);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);

        app(CurrentTenant::class)->run($this->a, function () {
            $this->kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            $this->club = Program::create(['title' => 'Club', 'slug' => 'club', 'type' => 'club']);
            $this->kursAngebot = Offer::create(['title' => 'Kurs kaufen', 'type' => 'course', 'access_days' => 365]);
            $this->kursAngebot->programs()->attach($this->kurs, ['tenant_id' => $this->a->id]);
            $this->kursAngebot->products()->create(['source' => 'woocommerce', 'external_id' => '1879']);
            $this->clubAngebot = Offer::create(['title' => 'Club-Abo', 'type' => 'club']);
            $this->clubAngebot->programs()->attach($this->club, ['tenant_id' => $this->a->id]);
            $this->clubAngebot->products()->create(['source' => 'woocommerce', 'external_id' => '1524']);
        });
    }

    protected function hook(array $payload, string $topic, string $secret = 'geheim', string $host = 'a.test')
    {
        $body = json_encode($payload);

        return $this->call('POST', "http://{$host}/hooks/woocommerce", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_TOPIC' => $topic,
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
        ], $body);
    }

    protected function order(string $status, array $products, string $email = 'neu@example.com', int $id = 501): array
    {
        return [
            'id' => $id, 'status' => $status, 'date_paid_gmt' => '2026-09-20T10:00:00',
            'billing' => ['email' => $email, 'first_name' => 'Nora', 'last_name' => 'Neu'],
            'line_items' => array_map(fn ($p) => ['product_id' => $p, 'variation_id' => 0, 'name' => 'x'], $products),
        ];
    }

    public function test_falsche_signatur_wird_abgewiesen(): void
    {
        $this->hook($this->order('completed', [1879]), 'order.updated', secret: 'falsch')->assertForbidden();
        $this->hook($this->order('completed', [1879]), 'order.updated', secret: 'geheim', host: 'b.test')->assertForbidden();
        $this->assertSame(0, User::where('email', 'neu@example.com')->count());
    }

    public function test_ping_ohne_json_ist_ok(): void
    {
        // Woo signiert den Ping nicht
        $this->call('POST', 'http://a.test/hooks/woocommerce', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], 'webhook_id=7')
            ->assertOk()->assertJson(['note' => 'ping']);
        // Alles andere ohne Signatur bleibt draussen
        $this->call('POST', 'http://a.test/hooks/woocommerce', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->order('completed', [1879])))->assertForbidden();

        $body = 'webhook_id=7';
        $this->call('POST', 'http://a.test/hooks/woocommerce', [], [], [], ['HTTP_X_WC_WEBHOOK_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, 'geheim', true))], $body)
            ->assertOk()->assertJson(['ok' => true]);
    }

    public function test_bestellung_legt_person_und_zugang_an(): void
    {
        $this->hook($this->order('completed', [1879, 9999]), 'order.updated')->assertOk()->assertJson(['status' => 'ok']);

        $user = User::where('email', 'neu@example.com')->first();
        $this->assertSame('Nora Neu', $user->name);
        $this->assertSame(Role::Member, $user->roleIn($this->a));
        Mail::assertSent(WillkommenMail::class, fn (WillkommenMail $m) => $m->hasTo('neu@example.com') && str_starts_with($m->url, 'http://a.test/anmelden/'));

        app(CurrentTenant::class)->run($this->a, function () use ($user) {
            $e = Entitlement::where('user_id', $user->id)->first();
            $this->assertSame('woocommerce', $e->source);
            $this->assertSame('order-501', $e->source_ref);
            $this->assertSame('2026-09-20', $e->starts_at->toDateString());
            $this->assertSame('2027-09-20', $e->ends_at->toDateString(), '365 Tage');
            $this->assertTrue(app(ProgramAccess::class)->canView($user, $this->kurs));
            $this->assertSame('ok', WebhookLog::first()->status);
        });

        // Nochmals dasselbe Ereignis: kein zweiter Zugang, keine zweite Mail
        $this->hook($this->order('completed', [1879]), 'order.updated')->assertOk();
        app(CurrentTenant::class)->run($this->a, fn () => $this->assertSame(1, Entitlement::count()));
        Mail::assertSentTimes(WillkommenMail::class, 1);
    }

    public function test_rueckerstattung_beendet_zugang_und_unbekanntes_produkt_wird_ignoriert(): void
    {
        $this->hook($this->order('completed', [1879]), 'order.updated');
        $this->hook($this->order('refunded', [1879]), 'order.updated')->assertJson(['status' => 'ok']);
        app(CurrentTenant::class)->run($this->a, function () {
            $e = Entitlement::first();
            $this->assertSame('cancelled', $e->status);
            $this->assertFalse($e->isCurrent());
        });

        $this->hook($this->order('completed', [4711], id: 502), 'order.updated')->assertJson(['status' => 'ignored']);
        $this->assertNull(User::where('email', 'x@example.com')->first());
    }

    public function test_abo_kommt_nur_ueber_subscription(): void
    {
        // Die Bestellung des Abo-Produkts gibt noch keinen Zugang
        $this->hook($this->order('completed', [1524], 'abo@example.com', 600), 'order.updated')->assertJson(['status' => 'ignored']);

        $sub = ['id' => 77, 'status' => 'active', 'start_date_gmt' => '2026-09-01T08:00:00', 'billing' => ['email' => 'abo@example.com', 'first_name' => 'Ada', 'last_name' => 'Abo'],
            'line_items' => [['product_id' => 1524, 'variation_id' => 0]]];
        $this->hook($sub, 'subscription.updated')->assertJson(['status' => 'ok']);
        $user = User::where('email', 'abo@example.com')->first();
        app(CurrentTenant::class)->run($this->a, function () use ($user) {
            $e = Entitlement::where('user_id', $user->id)->first();
            $this->assertSame('sub-77', $e->source_ref);
            $this->assertNull($e->ends_at);
            $this->assertTrue(app(ProgramAccess::class)->canView($user, $this->club));
        });

        $this->hook(array_merge($sub, ['status' => 'pending-cancel', 'end_date_gmt' => '2026-12-31T23:59:59']), 'subscription.updated');
        app(CurrentTenant::class)->run($this->a, fn () => $this->assertSame('2026-12-31', Entitlement::where('user_id', $user->id)->first()->ends_at->toDateString()));

        $this->hook(array_merge($sub, ['status' => 'cancelled']), 'subscription.updated');
        app(CurrentTenant::class)->run($this->a, function () use ($user) {
            $this->assertSame('cancelled', Entitlement::where('user_id', $user->id)->first()->status);
            $this->assertFalse(app(ProgramAccess::class)->canView($user, $this->club));
        });
    }

    public function test_im_testbetrieb_keine_willkommensmail_an_fremde(): void
    {
        $this->a->forceFill(['settings' => array_merge($this->a->settings, ['notifications' => ['test_only' => true, 'test_emails' => ['test@example.com']]])])->save();

        $this->hook($this->order('completed', [1879], 'neu@example.com', 700), 'order.updated')->assertJson(['status' => 'ok']);
        $this->hook($this->order('completed', [1879], 'test@example.com', 701), 'order.updated')->assertJson(['status' => 'ok']);

        Mail::assertNotSent(WillkommenMail::class, fn (WillkommenMail $m) => $m->hasTo('neu@example.com'));
        Mail::assertSent(WillkommenMail::class, fn (WillkommenMail $m) => $m->hasTo('test@example.com'));
        $this->assertNotNull(User::where('email', 'neu@example.com')->first(), 'Zugang trotzdem angelegt');
    }
}

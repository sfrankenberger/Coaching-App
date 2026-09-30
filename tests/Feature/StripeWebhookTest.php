<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_prueft_die_signatur_je_mandant(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['stripe' => ['webhook_secret' => 'whsec_test']]]);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);

        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed']);
        $t = time();
        $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');

        $this->call('POST', 'http://a.test/hooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk()->assertJson(['received' => true]);
        $this->call('POST', 'http://a.test/hooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1=falsch', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->call('POST', 'http://b.test/hooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Auth\Bridge;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['bridge' => ['secret' => 'bruecke-geheim']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'settings' => ['bridge' => ['secret' => 'anderes']]]);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);

        $this->anna = User::factory()->create(['email' => 'anna@example.com']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function token(string $email = 'Anna@Example.com', ?string $weiter = '/kurse', ?int $exp = null, string $secret = 'bruecke-geheim'): string
    {
        return app(CurrentTenant::class)->run($this->a, fn () => app(Bridge::class)->make($email, $weiter, $exp, $secret));
    }

    public function test_gueltiger_link_meldet_an_und_geht_nur_einmal(): void
    {
        $token = $this->token();
        $this->get('http://a.test/sso?token='.$token)->assertRedirect('/kurse');
        $this->assertAuthenticatedAs($this->anna);

        // Zweites Mal: verbraucht
        auth()->logout();
        $this->get('http://a.test/sso?token='.$token)->assertRedirect('/anmelden');
        $this->assertGuest();
    }

    public function test_abgelaufen_falsch_signiert_und_fremder_mandant(): void
    {
        $this->get('http://a.test/sso?token='.$this->token(exp: time() - 5))->assertRedirect('/anmelden');
        $this->assertGuest();

        $this->get('http://a.test/sso?token='.$this->token(secret: 'anderes'))->assertRedirect('/anmelden');
        $this->assertGuest();

        // Mit dem Geheimnis von B auf B: Anna hat dort keinen Zugang
        $this->get('http://b.test/sso?token='.$this->token(secret: 'anderes'))->assertRedirect('/anmelden');
        $this->assertGuest();

        $this->get('http://a.test/sso?token=kaputt')->assertRedirect('/anmelden');
        $this->get('http://a.test/sso?token='.$this->token(email: 'niemand@example.com'))->assertRedirect('/anmelden');
        $this->assertGuest();
    }

    public function test_weiter_nur_relativ(): void
    {
        $this->get('http://a.test/sso?token='.$this->token(weiter: 'https://boese.example/x'))->assertRedirect('/');
        $this->assertAuthenticatedAs($this->anna);
    }

    public function test_link_aus_dem_wordpress_snippet_wird_angenommen(): void
    {
        // Genau wie lea_neueapp_token() in docs/wordpress/lea-neueapp-bruecke.php
        $daten = rtrim(strtr(base64_encode(json_encode(['e' => 'anna@example.com', 't' => time() + 60, 'n' => bin2hex(random_bytes(8)), 'w' => '/kurse'])), '+/', '-_'), '=');
        $token = $daten.'.'.hash_hmac('sha256', $daten, 'bruecke-geheim');

        $this->get('http://a.test/sso?token='.rawurlencode($token))->assertRedirect('/kurse');
        $this->assertAuthenticatedAs($this->anna);
    }
}

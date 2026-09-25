<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['passkeys' => ['rp_id' => 'a.test']], 'branding' => ['app_name' => 'Coaching A']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->a->domains()->create(['domain' => 'app.a.test', 'is_primary' => false]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->anna = User::factory()->create(['email' => 'anna@example.com']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    public function test_optionen_zum_anlegen_tragen_die_relying_party_des_mandanten(): void
    {
        $r = $this->actingAs($this->anna)->postJson('http://app.a.test/passkeys/anlegen/optionen');
        $r->assertOk()->assertJsonPath('rp.id', 'a.test')->assertJsonPath('rp.name', 'Coaching A')->assertJsonPath('user.name', 'anna@example.com');
        $this->assertNotEmpty($r->json('challenge'));

        // Mandant B ohne Einstellung: die Domain selbst
        $bea = User::factory()->create();
        Tenant::where('slug', 'b')->first()->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->actingAs($bea)->postJson('http://b.test/passkeys/anlegen/optionen')->assertOk()->assertJsonPath('rp.id', 'b.test');

        $this->post('http://a.test/passkeys/anlegen/optionen')->assertRedirect();
    }

    public function test_optionen_zum_anmelden_und_falsche_antwort(): void
    {
        $r = $this->postJson('http://a.test/passkeys/anmelden/optionen', ['email' => 'anna@example.com']);
        $r->assertOk()->assertJsonPath('rpId', 'a.test');
        $this->assertNotEmpty($r->json('challenge'));
        $this->postJson('http://a.test/passkeys/anmelden/optionen')->assertOk()->assertJsonPath('rpId', 'a.test');

        $this->postJson('http://a.test/passkeys/anmelden', [
            'id' => 'abc', 'rawId' => 'YWJj', 'type' => 'public-key',
            'response' => ['clientDataJSON' => 'e30', 'authenticatorData' => 'AA', 'signature' => 'AA', 'userHandle' => null],
        ])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_anmeldeseite_und_profil_zeigen_passkeys(): void
    {
        $this->get('http://a.test/anmelden')->assertOk()->assertSee('Mit Passkey anmelden')->assertSee('passkeys.js');
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertSee('Passkey anlegen');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'branding' => ['app_name' => 'Coaching A', 'primary' => '#B4795F']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
    }

    protected function person(Tenant $tenant, Role $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role->value, 'status' => 'active']);

        return $user;
    }

    public function test_gast_landet_auf_der_anmeldung(): void
    {
        $this->get('http://a.test/coach')->assertRedirect('http://a.test/coach/login');
        $this->get('http://a.test/coach/login')->assertRedirect('http://a.test/anmelden?weiter=%2Fcoach');
    }

    public function test_teilnehmerin_darf_nicht_in_den_coach_bereich(): void
    {
        $this->actingAs($this->person($this->a, Role::Member))->get('http://a.test/coach')->assertForbidden();
    }

    public function test_owner_und_team_sehen_den_coach_bereich(): void
    {
        $this->actingAs($this->person($this->a, Role::Owner))->get('http://a.test/coach')->assertOk()->assertSee('Coaching A');
        $this->actingAs($this->person($this->a, Role::Team))->get('http://a.test/coach/memberships')->assertOk();
    }

    public function test_owner_von_a_sieht_nur_personen_von_a(): void
    {
        $owner = $this->person($this->a, Role::Owner);
        $this->person($this->a, Role::Member)->update(['name' => 'Anna von A']);
        $this->person($this->b, Role::Member)->update(['name' => 'Bea von B']);

        $this->actingAs($owner)->get('http://a.test/coach/memberships')
            ->assertOk()->assertSee('Anna von A')->assertDontSee('Bea von B');

        // Owner von A ist auf b.test niemand
        $this->actingAs($owner)->get('http://b.test/coach')->assertForbidden();
    }

    public function test_plattform_nur_fuer_plattform_admin(): void
    {
        $this->actingAs($this->person($this->a, Role::Owner))->get('http://a.test/plattform')->assertForbidden();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get('http://a.test/plattform/tenants')->assertOk()->assertSee('A')->assertSee('B');
    }
}

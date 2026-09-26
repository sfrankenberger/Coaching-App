<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_liefert_nur_mit_token_und_nur_im_eigenen_mandanten(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);
        app(CurrentTenant::class)->run($a, function () use ($anna) {
            $k = Program::create(['slug' => 'kurs', 'title' => 'Kurs A']);
            ProgramMember::create(['program_id' => $k->id, 'user_id' => $anna->id]);
            Event::create(['title' => 'Call A', 'type' => 'group_call', 'program_id' => $k->id, 'starts_at' => now()->addDay(), 'is_published' => true]);
        });

        $this->getJson('http://a.test/api/v1/ich')->assertUnauthorized();

        Sanctum::actingAs($anna, ['lesen']);
        $this->getJson('http://a.test/api/v1/ich')->assertOk()->assertJsonPath('vorname', 'Anna')->assertJsonPath('rolle', 'member');
        $this->getJson('http://a.test/api/v1/kurse')->assertOk()->assertJsonPath('data.0.titel', 'Kurs A');
        $this->getJson('http://a.test/api/v1/termine')->assertOk()->assertJsonPath('data.0.titel', 'Call A')->assertJsonPath('data.0.aufzeichnung', false);
        $this->getJson('http://b.test/api/v1/ich')->assertForbidden();
    }
}

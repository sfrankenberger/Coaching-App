<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SucheTest extends TestCase
{
    use RefreshDatabase;

    public function test_suche_findet_nur_was_die_person_sehen_darf(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => json_encode(['onboarding_seen_at' => now()->toIso8601String()])]);
        app(CurrentTenant::class)->run($a, function () use ($anna) {
            $mein = Program::create(['slug' => 'mein', 'title' => 'Mein Kurs']);
            $fremd = Program::create(['slug' => 'fremd', 'title' => 'Fremder Kurs']);
            ProgramMember::create(['program_id' => $mein->id, 'user_id' => $anna->id]);
            Unit::create(['program_id' => $mein->id, 'title' => 'Der Schockpunkt', 'body' => '<p>Wo alte Muster kippen.</p>', 'is_published' => true, 'position' => 1]);
            Unit::create(['program_id' => $fremd->id, 'title' => 'Schockpunkt geheim', 'body' => 'x', 'is_published' => true, 'position' => 1]);
            $e = Event::withoutEvents(fn () => Event::create(['tenant_id' => 1, 'title' => 'Call 3', 'type' => 'group_call', 'program_id' => $mein->id, 'starts_at' => now()->subWeek(), 'is_published' => true,
                'recording_url' => 'https://vimeo.com/1', 'transcript' => '[12:00] Heute reden wir über den Schockpunkt im Alltag.']));
            $r = Resource::create(['title' => 'Arbeitsblatt', 'type' => 'pdf', 'description' => 'Zum Schockpunkt']);
            $r->links()->create(['resourceable_type' => 'program', 'resourceable_id' => $mein->id]);
        });

        $r = $this->actingAs($anna)->get('http://a.test/suche?q=Schockpunkt')->assertOk();
        $r->assertSee('Der Schockpunkt')->assertSee('Call 3')->assertSee('Arbeitsblatt')->assertSee('<mark>Schockpunkt</mark>', false)->assertDontSee('Schockpunkt geheim');
        $this->actingAs($anna)->get('http://a.test/suche?q=Nirgendwo')->assertOk()->assertSee('nichts gefunden');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Entitlement;
use App\Models\Offer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilBuchungenTest extends TestCase
{
    use RefreshDatabase;

    public function test_meine_buchungen_mit_zugang_woche_und_sitzungen(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['shop' => ['account_url' => 'https://shop.test/konto/abos']]]);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);

        app(CurrentTenant::class)->run($a, function () use ($anna) {
            $kurs = Program::create(['slug' => 'kurs', 'title' => 'Wochenkurs', 'pacing' => 'weekly']);
            ProgramStep::create(['program_id' => $kurs->id, 'title' => 'W1', 'position' => 1, 'unlocks_at' => now()->subWeeks(2)]);
            ProgramStep::create(['program_id' => $kurs->id, 'title' => 'W2', 'position' => 2, 'unlocks_at' => now()->subDay()]);
            ProgramStep::create(['program_id' => $kurs->id, 'title' => 'W3', 'position' => 3, 'unlocks_at' => now()->addWeek()]);
            $einzel = Program::create(['slug' => 'einzel', 'title' => 'Einzel', 'type' => 'one_on_one', 'settings' => ['sitzungen_gesamt' => 5]]);
            ProgramMember::create(['program_id' => $einzel->id, 'user_id' => $anna->id]);
            $club = Offer::create(['title' => 'Club', 'type' => 'club']);
            $club->programs()->attach($kurs, ['tenant_id' => app(CurrentTenant::class)->id()]);
            Entitlement::create(['user_id' => $anna->id, 'offer_id' => $club->id, 'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonths(11)]);
            $alt = Offer::create(['title' => 'Alter Kurs', 'type' => 'course']);
            Entitlement::create(['user_id' => $anna->id, 'offer_id' => $alt->id, 'starts_at' => now()->subYears(2), 'ends_at' => now()->subYear(), 'status' => 'ended']);
        });

        $this->actingAs($anna)->get('http://a.test/profil')->assertOk()
            ->assertSee('Meine Buchungen')->assertSee('Sitzungen: 5 von 5 offen')
            ->assertSee('Club')->assertSee('Zugang bis')->assertSee('Wochenkurs: Woche 2 von 3')
            ->assertSee('https://shop.test/konto/abos')->assertSee('Alter Kurs')->assertSee('Beendet');
    }
}

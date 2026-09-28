<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ansehen als: der Plattform-Admin sieht die App wie Lea, wie das Team, wie jede Teilnehmerin. */
class AlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_wechselt_die_sicht_und_zurueck(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $seb = User::factory()->create(['name' => 'Sebastian Admin', 'is_platform_admin' => true]);
        $a->users()->attach($seb, ['role' => Role::Owner->value, 'status' => 'active']);
        $lea = User::factory()->create(['name' => 'Lea Coach']);
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $mia = User::factory()->create(['name' => 'Mia Muster']);
        $a->users()->attach($mia, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);

        $this->actingAs($seb)->get('http://a.test/als')->assertOk()->assertSee('Lea Coach')->assertSee('Mia Muster')->assertSee('Team')->assertSee('Teilnehmerinnen');
        $this->actingAs($seb)->get('http://a.test/')->assertOk()->assertSee('Ansehen als ...');

        // Als Mia: Teilnehmer-Sicht, Balken oben, ihr "zuletzt hier" bleibt unberuehrt
        $this->actingAs($seb)->post("http://a.test/als/{$mia->id}")->assertRedirect('http://a.test');
        $r = $this->actingAs($seb)->withSession(['als_user_id' => $mia->id])->get('http://a.test/')->assertOk();
        $r->assertSee('Hallo Mia')->assertSee('Du siehst die App als')->assertSee('Mia Muster')->assertSee('Zurück zu Sebastian')->assertSee('Übersicht')->assertDontSee('Guten Tag');
        $this->assertNull(app(CurrentTenant::class)->run($a, fn () => Membership::where('user_id', $mia->id)->first()->last_seen_at));

        // Als Lea: ihr Arbeitsplatz
        $this->actingAs($seb)->withSession(['als_user_id' => $lea->id])->get('http://a.test/')->assertOk()->assertSee('Guten Tag, Lea')->assertSee('Du siehst die App als');

        // Zurueck
        $this->actingAs($seb)->withSession(['als_user_id' => $lea->id])->delete('http://a.test/als')->assertRedirect('http://a.test/als')->assertSessionMissing('als_user_id');

        // Ohne Admin-Recht: kein Zugang, Sitzungsschluessel wird ignoriert
        $this->actingAs($lea)->get('http://a.test/als')->assertForbidden();
        $this->actingAs($lea)->post("http://a.test/als/{$mia->id}")->assertForbidden();
        $this->actingAs($lea)->withSession(['als_user_id' => $mia->id])->get('http://a.test/')->assertOk()->assertSee('Guten Tag, Lea')->assertDontSee('Du siehst die App als');
    }
}

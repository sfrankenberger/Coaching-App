<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Mitteilung;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MitteilungenTest extends TestCase
{
    use RefreshDatabase;

    public function test_jede_nachricht_landet_als_mitteilung_in_der_app_auch_im_testbetrieb(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['notifications' => ['test_only' => true, 'test_emails' => []]]]);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => json_encode(['onboarding_seen_at' => now()->toIso8601String()])]);
        $b->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => json_encode(['onboarding_seen_at' => now()->toIso8601String()])]);

        $report = app(CurrentTenant::class)->run($a, fn () => app(Notifier::class)->send([$anna->id], new Nachricht(
            titel: 'Neuer Termin: Call', text: 'Montag 20:00', url: 'http://a.test/termine/1', anlass: 'termin_neu',
        )));
        $this->assertSame([], $report[$anna->id], 'Testbetrieb: kein Push, keine Mail');
        $this->assertSame(1, app(CurrentTenant::class)->run($a, fn () => Mitteilung::count()));
        $this->assertSame(0, app(CurrentTenant::class)->run($b, fn () => Mitteilung::count()), 'Mandant B sieht nichts');

        // Schalter der Person gilt auch fuer die Glocke
        $m = $anna->membershipIn($a);
        $m->forceFill(['settings' => ['notifications' => ['termine' => false], 'onboarding_seen_at' => now()->toIso8601String()]])->save();
        app(CurrentTenant::class)->run($a, fn () => app(Notifier::class)->send([$anna->id], new Nachricht(titel: 'Nochmal', text: 'x', anlass: 'termin_neu')));
        $this->assertSame(1, app(CurrentTenant::class)->run($a, fn () => Mitteilung::count()));

        $this->actingAs($anna)->get('http://a.test/')->assertOk()->assertSee('fa-bell')->assertSee('<span class="zahl">1</span>', false);
        $this->actingAs($anna)->get('http://a.test/mitteilungen')->assertOk()->assertSee('Neuer Termin: Call')->assertSee('Montag 20:00');
        $this->assertSame(0, app(CurrentTenant::class)->run($a, fn () => Mitteilung::whereNull('read_at')->count()), 'aufrufen liest');
        $id = app(CurrentTenant::class)->run($a, fn () => Mitteilung::value('id'));
        $this->actingAs($anna)->get("http://a.test/mitteilungen/{$id}")->assertRedirect('http://a.test/termine/1');
        $this->actingAs($anna)->get('http://b.test/mitteilungen')->assertOk()->assertDontSee('Neuer Termin: Call');
    }
}

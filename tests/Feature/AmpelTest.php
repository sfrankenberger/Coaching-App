<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Coach\Lage;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmpelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = $this->person('Lea Coach', Role::Owner);
    }

    protected function person(string $name, Role $role, array $pivot = []): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->a->users()->attach($user, $pivot + ['role' => $role->value, 'status' => 'active', 'last_seen_at' => now()]);

        return $user;
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_wer_wartet_steht_zuerst_und_wer_still_ist_wird_gelb_oder_rot(): void
    {
        $gut = $this->person('Gina Gut', Role::Member);
        $still = $this->person('Stella Still', Role::Member, ['last_seen_at' => now()->subDays(9)]);
        $wartet = $this->person('Wanda Wartet', Role::Client);

        $this->in(function () use ($wartet) {
            $conv = app(Chat::class)->directFor($wartet);
            Message::create(['conversation_id' => $conv->id, 'user_id' => $this->lea->id, 'body' => 'Hallo Wanda']);
            Message::create(['conversation_id' => $conv->id, 'user_id' => $wartet->id, 'body' => 'Kurze Frage']);
            $conv->forceFill(['last_message_at' => now()])->save();
        });

        $alle = $this->in(fn () => app(Lage::class)->alle());
        $this->assertSame(['Wanda Wartet', 'Stella Still', 'Gina Gut'], $alle->pluck('user.name')->all());
        $this->assertSame(['rot', 'gelb', 'gruen'], $alle->pluck('farbe')->all());
        $this->assertSame('seit 9 Tagen nicht da', $alle[1]['grund']);

        // Nach der Antwort wartet niemand mehr
        $this->in(fn () => Message::create(['conversation_id' => app(Chat::class)->directFor($wartet)->id, 'user_id' => $this->lea->id, 'body' => 'Gern']));
        $this->assertTrue($this->in(fn () => app(Lage::class)->wartende())->isEmpty());

        $this->actingAs($this->lea)->get('http://a.test/coach')->assertOk()
            ->assertSee('Stella Still')->assertSee('Kurz nachfragen')->assertDontSee('Gina Gut');
        $this->actingAs($this->lea)->get('http://a.test/coach/memberships')->assertOk()
            ->assertSee('seit 9 Tagen nicht da')->assertSee('alles im Fluss');
    }

    public function test_kontingent_zaehlt_1_zu_1_termine(): void
    {
        $kim = $this->person('Kim Klientin', Role::Client);
        $k = $this->in(function () use ($kim) {
            $p = Program::create(['slug' => 'einzel', 'title' => 'Einzel', 'type' => 'one_on_one', 'settings' => ['sitzungen_gesamt' => 5]]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $kim->id, 'settings' => ['sitzungen_extra' => 1]]);
            Event::create(['title' => 'Sitzung 1', 'type' => 'one_on_one', 'user_id' => $kim->id, 'starts_at' => now()->subWeek(), 'is_published' => true]);
            Event::create(['title' => 'Sitzung 2', 'type' => 'one_on_one', 'user_id' => $kim->id, 'starts_at' => now()->addWeek(), 'is_published' => true]);

            return app(Lage::class)->kontingent($kim);
        });

        $this->assertSame(['gesamt' => 6, 'gehabt' => 1, 'geplant' => 1, 'offen' => 4], $k);
    }

    public function test_mandant_b_sieht_die_lage_von_a_nicht(): void
    {
        $this->person('Anna von A', Role::Member, ['last_seen_at' => now()->subDays(20)]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);

        $this->assertCount(1, $this->in(fn () => app(Lage::class)->alle()));
        $this->assertCount(0, app(CurrentTenant::class)->run($b, fn () => app(Lage::class)->alle()));
        $this->assertSame(1, $this->in(fn () => Membership::count()) - 1);
    }
}

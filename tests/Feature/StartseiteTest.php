<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartseiteTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        Tenant::create(['slug' => 'b', 'name' => 'B'])->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_einfuehrung_beim_ersten_besuch_und_danach_startseite(): void
    {
        $this->actingAs($this->anna)->get('http://a.test/')->assertRedirect('http://a.test/willkommen');
        $this->actingAs($this->anna)->get('http://a.test/willkommen')->assertOk()->assertSee('Hier bist du richtig')->assertSee('Schritt 1 von 8')->assertSee('Lea')->assertDontSee('data-willkommen-zu', false);

        $this->actingAs($this->anna)->post('http://a.test/willkommen', ['phone' => '079 111 22 33'])->assertRedirect('http://a.test');
        $this->assertSame('079 111 22 33', $this->anna->fresh()->phone);
        $this->assertNotNull($this->in(fn () => Membership::where('user_id', $this->anna->id)->first()->setting('onboarding_seen_at')));

        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertSee('Hallo Anna');
        $this->actingAs($this->anna)->get('http://a.test/willkommen')->assertOk()->assertSee('data-willkommen-zu', false);
        $this->actingAs($this->lea)->get('http://a.test/')->assertOk()->assertSee('Für dich als Coach');
    }

    public function test_startseite_zeigt_woche_termin_aufgaben_und_impuls(): void
    {
        $this->in(function () {
            $m = Membership::where('user_id', $this->anna->id)->first();
            $m->forceFill(['settings' => ['onboarding_seen_at' => now()->toIso8601String()], 'last_seen_at' => now()->subDays(2)])->save();
            $kurs = Program::create(['title' => 'Hybrid', 'slug' => 'hybrid', 'pacing' => 'weekly']);
            $s1 = ProgramStep::create(['program_id' => $kurs->id, 'title' => 'Woche 1: Boden', 'position' => 1, 'unlocks_at' => now()->subWeek()]);
            $s2 = ProgramStep::create(['program_id' => $kurs->id, 'title' => 'Woche 2: Rad', 'position' => 2, 'unlocks_at' => now()->subDay()]);
            ProgramStep::create(['program_id' => $kurs->id, 'title' => 'Woche 3: Zukunft', 'position' => 3, 'unlocks_at' => now()->addWeek()]);
            Unit::create(['program_id' => $kurs->id, 'step_id' => $s1->id, 'title' => 'Willkommen', 'position' => 1]);
            Unit::create(['program_id' => $kurs->id, 'step_id' => $s2->id, 'title' => 'Das Rad', 'position' => 2]);
            ProgramMember::create(['program_id' => $kurs->id, 'user_id' => $this->anna->id]);
            Event::create(['program_id' => $kurs->id, 'title' => 'Call morgen', 'starts_at' => now()->addDay()->setTime(19, 0)]);
            Event::create(['program_id' => $kurs->id, 'title' => 'Call gestern', 'starts_at' => now()->subDay()]);
            Task::create(['user_id' => $this->anna->id, 'title' => 'Buch lesen', 'due_at' => now()->subDay()->toDateString()]);
            Task::create(['user_id' => $this->anna->id, 'title' => 'Schon fertig', 'done_at' => now()]);
            Post::create(['title' => 'Der neue Impuls', 'published_at' => now()->subHour()]);
        });

        $r = $this->actingAs($this->anna)->get('http://a.test/');
        $r->assertOk()->assertSee('Diese Woche')->assertSee('Woche 2: Rad')->assertDontSee('Woche 3')->assertSee('Als Nächstes: Willkommen')
            ->assertSee('Nächster Termin')->assertSee('Call morgen')->assertDontSee('Call gestern')
            ->assertSee('Buch lesen')->assertDontSee('Schon fertig')->assertSee('Offene Aufgaben')
            ->assertSee('Der neue Impuls')->assertSee('Was ist neu')->assertSee('Neuer Termin: Call morgen')->assertSee('Alles gesehen');

        $this->assertTrue($this->in(fn () => Membership::where('user_id', $this->anna->id)->first()->last_seen_at->gt(now()->subMinute())), 'zuletzt gesehen aktualisiert');

        // "Alles gesehen" leert die Neu-Liste
        $this->actingAs($this->anna)->post('http://a.test/neu/gesehen')->assertRedirect('http://a.test');
        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertDontSee('Was ist neu')->assertSee('Call morgen');
    }

    public function test_kalender_abo_und_termin_datei(): void
    {
        $this->in(function () {
            Membership::where('user_id', $this->anna->id)->first()->forceFill(['settings' => ['onboarding_seen_at' => now()->toIso8601String()]])->save();
            $kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            ProgramMember::create(['program_id' => $kurs->id, 'user_id' => $this->anna->id]);
            Event::create(['program_id' => $kurs->id, 'title' => 'Gruppencall, Teil 1', 'starts_at' => now()->addDays(3)->setTime(19, 0), 'ends_at' => now()->addDays(3)->setTime(20, 30), 'zoom_url' => 'https://zoom.us/j/1']);
            Event::create(['title' => 'Fremd', 'starts_at' => now()->addDays(4)]);
            Event::create(['program_id' => $kurs->id, 'title' => 'Reflexionstag', 'type' => 'reflection_day', 'all_day' => true, 'starts_at' => now()->addDays(5)->startOfDay()]);
        });

        $profil = $this->actingAs($this->anna)->get('http://a.test/profil');
        $profil->assertOk()->assertSee('Kalender abonnieren')->assertSee('webcal://a.test/kalender/');
        $token = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first()->setting('calendar_token'));
        $this->assertNotEmpty($token);

        $ics = $this->get("http://a.test/kalender/{$token}.ics");
        $ics->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false)->assertSee('SUMMARY:Gruppencall\\, Teil 1', false)->assertSee('https://zoom.us/j/1', false)
            ->assertSee('DTSTART;VALUE=DATE:', false)->assertDontSee('Fremd');
        $this->get('http://b.test/kalender/'.$token.'.ics')->assertNotFound();
        $this->get('http://a.test/kalender/'.str_repeat('x', 40).'.ics')->assertNotFound();

        $event = $this->in(fn () => Event::where('title', 'Gruppencall, Teil 1')->first());
        $this->actingAs($this->anna)->get("http://a.test/termine/{$event->id}/kalender.ics")->assertOk()->assertSee('BEGIN:VEVENT', false);
        $fremd = $this->in(fn () => Event::where('title', 'Fremd')->first());
        $this->actingAs($this->anna)->get("http://a.test/termine/{$fremd->id}/kalender.ics")->assertForbidden();
    }
}

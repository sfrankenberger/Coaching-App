<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\MediaPosition;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Resource;
use App\Models\Resourceable;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Programs\ProgressTracker;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WochenseiteTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected User $fremd;

    protected Program $kurs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->fremd = User::factory()->create(['name' => 'Fremd B']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->b->users()->attach($this->fremd, ['role' => Role::Member->value, 'status' => 'active']);

        $this->kurs = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'pacing' => 'weekly']);
            $w1 = $p->steps()->create(['title' => 'Woche 1', 'position' => 1, 'week_number' => 1, 'unlocks_at' => now()->subDay()]);
            $u1 = $p->units()->create(['title' => 'Willkommen', 'step_id' => $w1->id, 'position' => 1, 'videos' => [['url' => 'https://vimeo.com/123456']]]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            Event::create(['program_id' => $p->id, 'step_id' => $w1->id, 'title' => 'Gruppencall Woche 1', 'starts_at' => now()->addDay()->setTime(19, 0), 'zoom_url' => 'https://zoom.us/j/1']);
            Event::create(['program_id' => $p->id, 'step_id' => $w1->id, 'title' => 'Reflexionstag', 'type' => 'reflection_day', 'all_day' => true, 'starts_at' => now()->addDays(3)]);
            $r = Resource::create(['title' => 'Handout Woche 1', 'type' => 'link', 'url' => 'https://example.ch/handout']);
            Resourceable::create(['resource_id' => $r->id, 'resourceable_type' => 'step', 'resourceable_id' => $w1->id]);
            $pdf = Resource::create(['title' => 'Arbeitsblatt', 'type' => 'pdf', 'url' => 'https://example.ch/blatt.pdf']);
            Resourceable::create(['resource_id' => $pdf->id, 'resourceable_type' => 'unit', 'resourceable_id' => $u1->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_wochenseite_zeigt_call_lektionen_aufgaben_material_und_reflexion(): void
    {
        [$w1, $u1] = $this->in(fn () => [$this->kurs->steps()->first(), Unit::first()]);

        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$w1->id}")->assertOk()
            ->assertSee('Gruppencall Woche 1')->assertSee('Zoom-Link')->assertSee('Willkommen')
            ->assertSee('Was nimmst du dir diese Woche vor?')->assertSee('Handout Woche 1')->assertSee('Arbeitsblatt')
            ->assertSee('Reflexion schreiben');

        // Vorhaben anlegen: gehoert zur Woche, zurueck auf die Wochenseite
        $zurueck = "http://a.test/kurse/hybrid/schritt/{$w1->id}";
        $this->actingAs($this->anna)->post('http://a.test/aufgaben', ['title' => 'Jeden Morgen fuenf Minuten still', 'program_id' => $this->kurs->id, 'step_id' => $w1->id, 'visibility' => 'coach', 'zurueck' => $zurueck])
            ->assertRedirect($zurueck);
        $t = $this->in(fn () => Task::where('title', 'Jeden Morgen fuenf Minuten still')->first());
        $this->assertSame($w1->id, $t->step_id);
        $this->actingAs($this->anna)->get($zurueck)->assertSee('Jeden Morgen fuenf Minuten still');

        // Fremde Adresse als Ruecksprung wird ignoriert
        $this->actingAs($this->anna)->post('http://a.test/aufgaben', ['title' => 'X', 'zurueck' => 'https://boese.example/'])->assertRedirect('http://a.test/aufgaben#aufgabe-'.($t->id + 1));

        // Einheit zeigt das PDF eingebettet
        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/einheit/{$u1->id}")->assertOk()->assertSee('Material dazu')->assertSee('blatt.pdf#view=FitH', false);
    }

    public function test_videoposition_und_ab_80_prozent_erledigt(): void
    {
        [$u1, $call] = $this->in(fn () => [Unit::first(), Event::where('title', 'Gruppencall Woche 1')->first()]);

        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => 'unit-'.$u1->id, 'seconds' => 125, 'duration' => 600])->assertJson(['erledigt' => false]);
        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/einheit/{$u1->id}")->assertSee('Du warst bei 02:05')->assertSee('data-start="125"', false);

        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => 'unit-'.$u1->id, 'seconds' => 500, 'duration' => 600])->assertJson(['erledigt' => true]);
        $this->assertTrue($this->in(fn () => app(ProgressTracker::class)->completedUnitIds($this->anna, $this->kurs)->contains($u1->id)));

        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => 'event-'.$call->id, 'seconds' => 3000, 'duration' => 3600])->assertJson(['erledigt' => true]);
        $this->assertSame('watched', $this->in(fn () => EventAttendee::where('user_id', $this->anna->id)->first()->status));

        // Mandant B: kein Zugriff, und die Positionen von A bleiben unsichtbar
        $this->actingAs($this->fremd)->postJson('http://b.test/medien/position', ['key' => 'unit-'.$u1->id, 'seconds' => 1, 'duration' => 10])->assertForbidden();
        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => 'kaputt-1', 'seconds' => 1])->assertUnprocessable();
        app(CurrentTenant::class)->run($this->b, fn () => $this->assertSame(0, MediaPosition::count()));
        $this->assertSame(2, $this->in(fn () => MediaPosition::count()));
    }

    public function test_kursaufgaben_kommen_auch_zu_spaeter_eintretenden(): void
    {
        $w1 = $this->in(fn () => $this->kurs->steps()->first());
        $this->in(function () use ($w1) {
            Task::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'step_id' => $w1->id, 'title' => 'Intention aufschreiben', 'source' => 'program', 'assigned_by' => $this->lea->id, 'visibility' => 'coach']);
            Task::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'title' => 'Alte Frist', 'source' => 'program', 'assigned_by' => $this->lea->id, 'due_at' => now()->subWeek()->toDateString()]);
            Task::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'title' => 'Eigenes aus Einheit', 'source' => 'program']);
        });

        $bea = User::factory()->create();
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->in(fn () => ProgramMember::create(['program_id' => $this->kurs->id, 'user_id' => $bea->id]));

        $titel = $this->in(fn () => Task::where('user_id', $bea->id)->pluck('title')->all());
        $this->assertSame(['Intention aufschreiben'], $titel);
        $this->assertSame($w1->id, $this->in(fn () => Task::where('user_id', $bea->id)->first()->step_id));
    }
}

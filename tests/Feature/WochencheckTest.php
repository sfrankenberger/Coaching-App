<?php

namespace Tests\Feature;

use App\Coach\Wochencheck;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Question;
use App\Models\Reflection;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WochencheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_aktuelle_und_naechste_woche_mit_haken(): void
    {
        $this->travelTo(Carbon::parse('2026-09-24 10:00:00', 'UTC'));   // Donnerstag
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich', 'settings' => ['wochencheck' => ['haken' => ['post' => 'Post gesichtet']]]]);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $lea = User::factory()->create(['name' => 'Lea Coach']);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);
        $a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $cur = app(CurrentTenant::class);

        $cur->run($a, function () use ($anna, $bea) {
            $p = Program::create(['slug' => 'kurs', 'title' => 'Wochenkurs', 'pacing' => 'weekly', 'is_published' => true]);
            $w1 = ProgramStep::create(['program_id' => $p->id, 'title' => 'Ankommen', 'position' => 1, 'unlocks_at' => '2026-09-20 22:00:00', 'summary' => '<p>Hallo</p>']);
            $w2 = ProgramStep::create(['program_id' => $p->id, 'title' => 'Boden', 'position' => 2, 'unlocks_at' => '2026-09-27 22:00:00']);
            Event::create(['title' => 'Call 1', 'type' => 'group_call', 'program_id' => $p->id, 'step_id' => $w1->id, 'starts_at' => '2026-09-22 17:00:00', 'is_published' => true]);
            Event::create(['title' => 'Call 2', 'type' => 'group_call', 'program_id' => $p->id, 'step_id' => $w2->id, 'starts_at' => '2026-09-29 17:00:00', 'zoom_url' => 'https://zoom.us/j/2', 'is_published' => true]);
            foreach ([$anna, $bea] as $u) {
                ProgramMember::create(['program_id' => $p->id, 'user_id' => $u->id]);
            }
            Reflection::create(['user_id' => $anna->id, 'went_well' => 'x']);
            Question::create(['program_id' => $p->id, 'user_id' => $bea->id, 'title' => 'Wie?', 'visibility' => 'program']);
        });

        $programme = $cur->run($a, fn () => app(Wochencheck::class)->programme());
        $this->assertCount(1, $programme);
        [$jetzt, $naechste] = $programme[0]['wochen'];
        $texte = collect($jetzt['zeilen'])->pluck(1);
        $this->assertStringContainsString('Diese Woche: Ankommen', $jetzt['titel']);
        $this->assertTrue($texte->contains(fn ($t) => str_contains($t, 'Zoom-Link fehlt')));
        $this->assertTrue($texte->contains('Einleitung ist eingetragen'));
        $this->assertTrue($texte->contains('Aufzeichnung fehlt noch'));
        $this->assertTrue($texte->contains('Reflexionen: 1 von 2 (noch offen: Bea)'));
        $this->assertTrue($texte->contains('Eine offene Frage ohne Antwort'));
        $this->assertTrue(collect($naechste['zeilen'])->pluck(1)->contains(fn ($t) => str_contains($t, 'Zoom-Link steht')));
        $this->assertTrue(collect($naechste['zeilen'])->pluck(1)->contains('Einleitung fehlt'));

        $cur->run($a, fn () => app(Wochencheck::class)->abhaken('post', true, $lea));
        $this->assertSame($lea->id, $cur->run($a, fn () => app(Wochencheck::class)->haken()['post'][1]));

        $this->actingAs($lea)->get('http://a.test/coach')->assertOk()->assertSee('Wochencheck')->assertSee('Post gesichtet')->assertSee('Zoom-Link fehlt');
    }
}

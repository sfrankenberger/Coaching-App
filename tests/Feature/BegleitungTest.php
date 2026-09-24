<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Bookmark;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Reflection;
use App\Models\Resource;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BegleitungTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected User $fremd;

    protected Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->anna = User::factory()->create();
        $this->fremd = User::factory()->create();
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->fremd, ['role' => Role::Member->value, 'status' => 'active']);
        $this->program = $this->in(function () {
            $p = Program::create(['slug' => 'kurs', 'title' => 'Kurs A']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_termine_nur_aus_eigenen_programmen_und_eigene_1_zu_1(): void
    {
        [$call, $einzel, $fremdEinzel] = $this->in(fn () => [
            Event::create(['program_id' => $this->program->id, 'title' => 'Gruppencall', 'starts_at' => now()->addDay(), 'zoom_url' => 'https://zoom.us/j/1']),
            Event::create(['title' => 'Sitzung Anna', 'type' => 'one_on_one', 'user_id' => $this->anna->id, 'starts_at' => now()->addDays(2)]),
            Event::create(['title' => 'Sitzung Fremd', 'type' => 'one_on_one', 'user_id' => $this->fremd->id, 'starts_at' => now()->addDays(3)]),
        ]);

        $this->actingAs($this->anna)->get('http://a.test/termine')->assertOk()->assertSee('Gruppencall')->assertSee('Sitzung Anna')->assertDontSee('Sitzung Fremd');
        $this->actingAs($this->fremd)->get('http://a.test/termine')->assertOk()->assertDontSee('Gruppencall')->assertSee('Sitzung Fremd');
        $this->actingAs($this->fremd)->get("http://a.test/termine/{$call->id}")->assertForbidden();
        $this->actingAs($this->anna)->get("http://a.test/termine/{$call->id}")->assertOk()->assertSee('zoom.us');

        $this->actingAs($this->anna)->post("http://a.test/termine/{$call->id}/dabei")->assertRedirect();
        $this->assertSame('declined', $this->in(fn () => EventAttendee::where('event_id', $call->id)->where('user_id', $this->anna->id)->value('status')));
        $this->actingAs($this->anna)->post("http://a.test/termine/{$einzel->id}/dabei")->assertStatus(422);

        $this->in(fn () => $call->update(['starts_at' => now()->subDays(3), 'recording_url' => 'https://vimeo.com/987']));
        $this->actingAs($this->anna)->get("http://a.test/termine/{$call->id}")->assertOk()->assertSee('player.vimeo.com/video/987');
        $this->actingAs($this->anna)->postJson("http://a.test/termine/{$call->id}/gesehen")->assertOk()->assertJsonPath('status', 'watched');
    }

    public function test_material_aus_programm_termin_und_geteilt(): void
    {
        $this->in(function () {
            $r1 = Resource::create(['title' => 'Arbeitsblatt', 'type' => 'pdf', 'url' => 'https://example.com/a.pdf']);
            $r1->programs()->attach($this->program->id, ['tenant_id' => $this->a->id]);
            $r2 = Resource::create(['title' => 'Nur für Anna', 'type' => 'link', 'url' => 'https://example.com']);
            $r2->users()->attach($this->anna->id, ['tenant_id' => $this->a->id]);
            $r3 = Resource::create(['title' => 'Geheim', 'type' => 'pdf', 'url' => 'https://example.com/g.pdf']);
            $e = Event::create(['program_id' => $this->program->id, 'title' => 'Alter Call', 'starts_at' => now()->subWeek(), 'recording_url' => 'https://vimeo.com/1']);
        });

        $this->actingAs($this->anna)->get('http://a.test/material')->assertOk()->assertSee('Arbeitsblatt')->assertSee('Nur für Anna')->assertSee('Alter Call')->assertDontSee('Geheim');
        $this->actingAs($this->fremd)->get('http://a.test/material')->assertOk()->assertDontSee('Arbeitsblatt')->assertDontSee('Nur für Anna');

        $r = $this->in(fn () => Resource::where('title', 'Arbeitsblatt')->first());
        $this->actingAs($this->anna)->postJson('http://a.test/merken', ['type' => 'resource', 'id' => $r->id])->assertOk()->assertJsonPath('an', true);
        $this->assertSame(1, $this->in(fn () => Bookmark::where('user_id', $this->anna->id)->count()));
        $this->actingAs($this->anna)->get('http://a.test/material?f=gemerkt')->assertOk()->assertSee('Arbeitsblatt')->assertDontSee('Nur für Anna');
        $this->actingAs($this->anna)->get('http://a.test/material?f=aufzeichnung')->assertOk()->assertSee('Alter Call')->assertDontSee('Arbeitsblatt');
    }

    public function test_aufgaben_anlegen_abhaken_tage_loeschen(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/aufgaben', ['title' => 'Intention schreiben', 'due_at' => now()->addDay()->toDateString(), 'is_daily' => 1, 'program_id' => $this->program->id, 'visibility' => 'program'])->assertRedirect();
        $t = $this->in(fn () => Task::where('user_id', $this->anna->id)->first());
        $this->assertSame('program', $t->visibility);
        $this->assertTrue($t->is_daily);

        $this->actingAs($this->anna)->get('http://a.test/aufgaben')->assertOk()->assertSee('Intention schreiben')->assertSee('0 von 7');
        $this->actingAs($this->anna)->postJson("http://a.test/aufgaben/{$t->id}/tag", ['tag' => 'mo'])->assertOk()->assertJsonPath('tage', ['mo']);
        $this->actingAs($this->anna)->postJson("http://a.test/aufgaben/{$t->id}/haken")->assertOk()->assertJsonPath('an', true);
        $this->assertNotNull($t->fresh()->done_at);

        $this->actingAs($this->fremd)->postJson("http://a.test/aufgaben/{$t->id}/haken")->assertForbidden();
        $this->actingAs($this->fremd)->delete("http://a.test/aufgaben/{$t->id}")->assertForbidden();
        $this->actingAs($this->anna)->delete("http://a.test/aufgaben/{$t->id}")->assertRedirect();
        $this->assertSame(0, $this->in(fn () => Task::count()));
    }

    public function test_notizen_und_reflexion(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Merken: Pausen machen', 'visibility' => 'coach'])->assertRedirect();
        $n = $this->in(fn () => Note::where('user_id', $this->anna->id)->first());
        $this->assertSame('coach', $n->visibility);
        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()->assertSee('Pausen machen');
        $this->actingAs($this->fremd)->get('http://a.test/notizen')->assertOk()->assertDontSee('Pausen machen');
        $this->actingAs($this->fremd)->post("http://a.test/notizen/{$n->id}", ['body' => 'Hack'])->assertForbidden();

        $this->actingAs($this->anna)->post('http://a.test/reflexion', ['went_well' => 'Viel', 'visibility' => 'private'])->assertRedirect();
        $r = $this->in(fn () => Reflection::where('user_id', $this->anna->id)->first());
        $this->assertSame('private', $r->visibility);
        // weiterschreiben statt neu
        $this->actingAs($this->anna)->get('http://a.test/reflexion')->assertOk()->assertSee('Schreib einfach weiter');
        $this->actingAs($this->anna)->post('http://a.test/reflexion', ['refl_id' => $r->id, 'went_well' => 'Viel', 'focus' => 'Weniger', 'visibility' => 'coach'])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Reflection::count()));
        $this->assertSame('coach', $r->fresh()->visibility);
        $this->assertNotNull($r->fresh()->shared_at);
        $this->actingAs($this->anna)->post("http://a.test/reflexion/{$r->id}/nachtrag", ['addendum' => 'Noch was'])->assertRedirect();
        $this->assertSame('Noch was', $r->fresh()->addendum);

        $this->actingAs($this->anna)->get('http://a.test/journal')->assertOk()->assertSee('Meine Aufgaben')->assertSee('Wochenreflexion');
    }
}

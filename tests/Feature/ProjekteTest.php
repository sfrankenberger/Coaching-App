<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Projekt;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Meine Projekte mit den neun Schritten und die Zeitleiste im Journal. */
class ProjekteTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected User $fremd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->fremd = User::factory()->create(['name' => 'Fritz Fremd']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->b->users()->attach($this->fremd, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_projekt_anlegen_schritt_setzen_treppe_und_zuordnung(): void
    {
        $this->actingAs($this->anna)->get('http://a.test/projekte')->assertOk()->assertSee('Die neun Schritte')->assertSee('Noch kein Projekt');
        $this->actingAs($this->anna)->post('http://a.test/projekte', ['name' => 'Mein Nebenerwerb', 'worum' => 'Erste Kundinnen finden', 'farbe' => '#6E8B74', 'icon' => 'briefcase', 'schritt' => 'intention'])->assertRedirect();
        $p = $this->in(fn () => Projekt::first());
        $this->assertSame('intention', $p->schritt);

        // Treppe: das Projekt steht auf Stufe 1, Verschieben nach "Volle Aktion"
        $this->actingAs($this->anna)->get('http://a.test/projekte')->assertOk()->assertSee('Mein Nebenerwerb')->assertSee('Wo ich stehe');
        $this->actingAs($this->anna)->post("http://a.test/projekte/{$p->id}/schritt", ['schritt' => 'aktion'])->assertRedirect();
        $this->assertSame('aktion', $p->fresh()->schritt);
        $this->actingAs($this->anna)->postJson("http://a.test/projekte/{$p->id}/schritt", ['schritt' => 'unfug'])->assertStatus(422);

        // Notiz und Aufgabe gehoeren zum Projekt, Chip und Filter
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Erste Idee fuer den Flyer', 'project_id' => $p->id])->assertRedirect();
        $this->actingAs($this->anna)->post('http://a.test/aufgaben', ['title' => 'Flyer drucken', 'project_id' => $p->id])->assertRedirect();
        $this->actingAs($this->anna)->post('http://a.test/aufgaben', ['title' => 'Ohne Projekt'])->assertRedirect();
        $this->assertSame($p->id, $this->in(fn () => Note::first()->project_id));
        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()->assertSee('Mein Nebenerwerb');
        $this->actingAs($this->anna)->get("http://a.test/journal?projekt={$p->id}")->assertOk()->assertSee('Erste Idee')->assertSee('Flyer drucken')->assertDontSee('Ohne Projekt');

        // Projekt entfernen: es liegt im Papierkorb, die Eintraege bleiben und behalten die Zuordnung bis zum endgueltigen Loeschen
        $this->actingAs($this->anna)->delete("http://a.test/projekte/{$p->id}")->assertRedirect();
        $this->assertSame(0, $this->in(fn () => Projekt::count()));
        $this->assertSame(1, $this->in(fn () => Projekt::onlyTrashed()->count()));
        $this->assertSame($p->id, $this->in(fn () => Note::first()->project_id));
        $this->assertSame(2, $this->in(fn () => Task::count()));
        $this->in(fn () => Projekt::onlyTrashed()->first()->forceDelete());
        $this->assertNull($this->in(fn () => Note::first()->project_id));
        $this->assertSame(2, $this->in(fn () => Task::count()));
    }

    public function test_fremdes_projekt_und_anderer_mandant(): void
    {
        $p = $this->in(fn () => Projekt::create(['user_id' => $this->lea->id, 'name' => 'Leas Projekt']));
        $this->actingAs($this->anna)->post("http://a.test/projekte/{$p->id}/schritt", ['schritt' => 'aktion'])->assertForbidden();
        $this->actingAs($this->anna)->delete("http://a.test/projekte/{$p->id}")->assertForbidden();
        // Fremdes Projekt laesst sich nicht zuordnen
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Versuch', 'project_id' => $p->id])->assertRedirect();
        $this->assertNull($this->in(fn () => Note::first()->project_id));
        // Mandant B sieht nichts von A
        $this->actingAs($this->fremd)->get('http://b.test/projekte')->assertOk()->assertDontSee('Leas Projekt');
        $this->actingAs($this->fremd)->post("http://b.test/projekte/{$p->id}/schritt", ['schritt' => 'aktion'])->assertNotFound();
    }

    public function test_zeitleiste_mit_monat_woche_arten_und_als_naechstes(): void
    {
        $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'type' => 'hybrid']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            Event::create(['program_id' => $p->id, 'title' => 'Call morgen', 'starts_at' => now()->addDay()]);
            $alt = Event::create(['program_id' => $p->id, 'title' => 'Alter Call', 'starts_at' => now()->subDays(10), 'recording_url' => 'https://vimeo.com/1']);
            Note::create(['user_id' => $this->anna->id, 'body' => 'Notiz von heute']);
            Task::create(['user_id' => $this->anna->id, 'title' => 'Aufgabe von heute']);
            $r = Reflection::create(['user_id' => $this->anna->id, 'week_label' => 'Woche 30', 'went_well' => 'Lief gut']);
            $r->forceFill(['created_at' => now()->subDays(45)])->saveQuietly();
            Note::create(['user_id' => $this->lea->id, 'body' => 'Leas private Notiz']);
        });
        $r = $this->actingAs($this->anna)->get('http://a.test/journal')->assertOk()
            ->assertSee('Als Nächstes: Call morgen')->assertSee('Notiz von heute')->assertSee('Aufgabe von heute')->assertSee('Alter Call')->assertSee('Lief gut')
            ->assertSee('Diese Woche')->assertSee(now()->translatedFormat('F Y'))->assertSee(now()->subDays(45)->translatedFormat('F Y'))
            ->assertDontSee('Leas private Notiz');
        $this->actingAs($this->anna)->get('http://a.test/journal?art=notiz')->assertOk()->assertSee('Notiz von heute')->assertDontSee('Aufgabe von heute')->assertDontSee('Alter Call');
        $this->actingAs($this->anna)->get('http://a.test/journal?art=aufzeichnung')->assertOk()->assertSee('Alter Call')->assertDontSee('Notiz von heute');
        $this->actingAs($this->anna)->get('http://a.test/journal')->assertOk()->assertSee('Meine Zeitleiste')->assertSee('Meine Projekte');
    }
}

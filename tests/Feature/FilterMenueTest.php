<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Projekt;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Filterleiste (Zeit, Projekt, Kurs, Status, Suche) und Dreipunkt-Menue (Anpinnen, Teilen mit, Projekt). */
class FilterMenueTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected Program $hybrid;

    protected Projekt $projekt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea', 'features' => ['zeitleiste' => true, 'projekte' => true]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        [$this->hybrid, $this->projekt] = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return [$p, Projekt::create(['user_id' => $this->anna->id, 'name' => 'Nebenerwerb', 'farbe' => '#6E8B74'])];
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_filterleiste_bei_aufgaben(): void
    {
        [$alt, $pj, $kurs, $fertig] = $this->in(function () {
            $alt = Task::create(['user_id' => $this->anna->id, 'title' => 'Alte Aufgabe']);
            $alt->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();
            $pj = Task::create(['user_id' => $this->anna->id, 'title' => 'Projektaufgabe', 'project_id' => $this->projekt->id]);
            $kurs = Task::create(['user_id' => $this->anna->id, 'title' => 'Kursaufgabe', 'program_id' => $this->hybrid->id]);
            $fertig = Task::create(['user_id' => $this->anna->id, 'title' => 'Fertige Aufgabe', 'done_at' => now()]);
            Comment::create(['user_id' => $this->lea->id, 'commentable_type' => 'task', 'commentable_id' => $fertig->id, 'body' => 'Super']);

            return [$alt, $pj, $kurs, $fertig];
        });
        // Die Anhang-Auswahl im Formular nennt alle Titel, darum wird auf die Karten geprueft
        $karte = fn ($t) => 'id="aufgabe-'.$t->id.'"';
        $this->actingAs($this->anna)->get('http://a.test/aufgaben')->assertOk()->assertSee('Alles wird gezeigt')->assertSee($karte($alt), false)->assertSee($karte($pj), false);
        $this->actingAs($this->anna)->get('http://a.test/aufgaben?zeit=30')->assertOk()->assertSee('Gefiltert: Letzte 30 Tage')->assertDontSee($karte($alt), false)->assertSee($karte($pj), false);
        $this->actingAs($this->anna)->get("http://a.test/aufgaben?projekt={$this->projekt->id}")->assertOk()->assertSee($karte($pj), false)->assertDontSee($karte($kurs), false)->assertSee('Gefiltert: Nebenerwerb');
        $this->actingAs($this->anna)->get('http://a.test/aufgaben?projekt=ohne')->assertOk()->assertDontSee($karte($pj), false)->assertSee($karte($kurs), false);
        $this->actingAs($this->anna)->get("http://a.test/aufgaben?kurs={$this->hybrid->id}")->assertOk()->assertSee($karte($kurs), false)->assertDontSee($karte($pj), false);
        $this->actingAs($this->anna)->get('http://a.test/aufgaben?st=erledigt')->assertOk()->assertSee($karte($fertig), false)->assertDontSee($karte($kurs), false);
        $this->actingAs($this->anna)->get('http://a.test/aufgaben?st=neu')->assertOk()->assertSee($karte($fertig), false)->assertDontSee($karte($kurs), false);
        $this->actingAs($this->anna)->get('http://a.test/aufgaben?q=kurs')->assertOk()->assertSee($karte($kurs), false)->assertDontSee($karte($pj), false);
        // Notizen und Reflexionen haben dieselbe Leiste
        $this->actingAs($this->anna)->get('http://a.test/notizen?zeit=woche')->assertOk()->assertSee('Gefiltert: Diese Woche');
        $this->actingAs($this->anna)->get('http://a.test/reflexion?q=nichts')->assertOk()->assertSee('Gefiltert: «nichts»');
    }

    public function test_dreipunkt_menue_anpinnen_teilen_projekt(): void
    {
        Notification::fake();
        $n = $this->in(fn () => Note::create(['user_id' => $this->anna->id, 'body' => 'Mein Gedanke']));
        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()->assertSee('Anpinnen')->assertSee('Teilen mit')->assertSee('Nebenerwerb')->assertSee('Hybrid-Coaching');

        $this->actingAs($this->anna)->post("http://a.test/element/note/{$n->id}/schnell", ['was' => 'pin'])->assertRedirect();
        $this->assertTrue($this->in(fn () => $n->fresh()->is_pinned));

        $this->actingAs($this->anna)->post("http://a.test/element/note/{$n->id}/schnell", ['was' => 'projekt', 'projekt' => $this->projekt->id])->assertRedirect();
        $this->assertSame($this->projekt->id, $this->in(fn () => $n->fresh()->project_id));

        $this->actingAs($this->anna)->post("http://a.test/element/note/{$n->id}/schnell", ['was' => 'teilen', 'sicht' => 'program', 'kurs' => $this->hybrid->id])->assertRedirect();
        $f = $this->in(fn () => $n->fresh());
        $this->assertSame('program', $f->visibility);
        $this->assertSame($this->hybrid->id, $f->program_id);
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($x) => $x->nachricht->titel === 'Anna teilt eine Notiz mit dir');

        // Reflexion teilen und zurueck
        $r = $this->in(fn () => Reflection::create(['user_id' => $this->anna->id, 'week_label' => 'Woche 40', 'went_well' => 'Gut']));
        $this->actingAs($this->anna)->post("http://a.test/element/reflection/{$r->id}/schnell", ['was' => 'teilen', 'sicht' => 'coach'])->assertRedirect();
        $this->assertNotNull($this->in(fn () => $r->fresh()->shared_at));
        $this->actingAs($this->anna)->post("http://a.test/element/reflection/{$r->id}/schnell", ['was' => 'teilen', 'sicht' => 'private'])->assertRedirect();
        $this->assertNull($this->in(fn () => $r->fresh()->shared_at));

        // Fremdes nicht
        $this->actingAs($this->lea)->post("http://a.test/element/note/{$n->id}/schnell", ['was' => 'pin'])->assertNotFound();
    }
}

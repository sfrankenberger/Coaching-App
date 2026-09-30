<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Reaction;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Geteilt aus dem Kurs: was jemand fuer Kurs oder Community freigibt, sehen die anderen, mit Reaktionen und Kommentaren. */
class GeteiltTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected User $carla;

    protected Program $hybrid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->carla = User::factory()->create(['name' => 'Carla Ohne']);
        foreach ([[$this->lea, Role::Owner], [$this->anna, Role::Member], [$this->bea, Role::Member], [$this->carla, Role::Member]] as [$u, $r]) {
            $this->a->users()->attach($u, ['role' => $r->value, 'status' => 'active']);
        }
        $this->hybrid = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->bea->id]);
            $anfang = Program::create(['slug' => 'anfang', 'title' => 'Der Anfang', 'type' => 'selfpaced']);
            ProgramMember::create(['program_id' => $anfang->id, 'user_id' => $this->carla->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_geteiltes_erscheint_in_der_community_fuer_die_gruppe(): void
    {
        Notification::fake();
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Mein Aha-Moment diese Woche', 'program_id' => $this->hybrid->id, 'visibility' => 'program'])->assertRedirect();
        $this->in(function () {
            Task::create(['user_id' => $this->bea->id, 'title' => 'Beas Community-Vorhaben', 'visibility' => 'all']);
            Reflection::create(['user_id' => $this->anna->id, 'week_label' => 'Woche 40', 'went_well' => 'Vieles lief gut', 'program_id' => $this->hybrid->id, 'visibility' => 'program', 'shared_at' => now()]);
            Note::create(['user_id' => $this->anna->id, 'body' => 'Nur fuer Lea', 'visibility' => 'coach']);
            Note::create(['user_id' => $this->lea->id, 'body' => 'Leas Hinweis an alle', 'program_id' => $this->hybrid->id, 'visibility' => 'program']);
        });

        // Bea (im Hybrid) sieht Annas Notiz und Reflexion, nicht ihre eigene Aufgabe, nichts von Lea, nichts Privates
        $this->actingAs($this->bea)->get('http://a.test/community')->assertOk()
            ->assertSee('Geteilt aus dem Kurs')->assertSee('Mein Aha-Moment')->assertSee('Vieles lief gut')
            ->assertDontSee('Beas Community-Vorhaben')->assertDontSee('Leas Hinweis')->assertDontSee('Nur fuer Lea');
        // Carla (nur im Selbstlernkurs) sieht nur, was "in der Community" steht
        $this->actingAs($this->carla)->get('http://a.test/community')->assertOk()
            ->assertSee('Beas Community-Vorhaben')->assertDontSee('Mein Aha-Moment');
        // Anna sieht Beas Aufgabe, nicht ihre eigenen Eintraege
        $this->actingAs($this->anna)->get('http://a.test/community')->assertOk()->assertSee('Beas Community-Vorhaben')->assertDontSee('Mein Aha-Moment');

        // Lea hat erfahren, dass Anna eine Notiz teilt, und sieht es in der Arbeitsliste
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => $n->nachricht->titel === 'Anna teilt eine Notiz mit dir');
        $this->actingAs($this->lea)->get('http://a.test/')->assertOk()->assertSee('Anna Muster hat eine Notiz geteilt');
    }

    public function test_reaktion_und_kommentar_der_gruppe(): void
    {
        Notification::fake();
        $note = $this->in(fn () => Note::create(['user_id' => $this->anna->id, 'body' => 'Mein Aha-Moment', 'program_id' => $this->hybrid->id, 'visibility' => 'program']));

        $this->actingAs($this->bea)->post("http://a.test/reaktion/note/{$note->id}", ['emoji' => 'herz'])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Reaction::where('reactable_type', 'note')->where('reactable_id', $note->id)->count()));
        $this->actingAs($this->bea)->postJson("http://a.test/reaktion/note/{$note->id}", ['emoji' => 'herz'])->assertOk();
        $this->assertSame(0, $this->in(fn () => Reaction::where('reactable_type', 'note')->count()), 'nochmal druecken nimmt die Reaktion zurueck');
        // Auf Eigenes nicht, Fremde (nicht im Kurs) nicht
        $this->actingAs($this->anna)->post("http://a.test/reaktion/note/{$note->id}", ['emoji' => 'herz'])->assertStatus(422);
        $this->actingAs($this->carla)->post("http://a.test/reaktion/note/{$note->id}", ['emoji' => 'herz'])->assertNotFound();

        // Kommentar aus der Gruppe: Anna erfaehrt es
        $this->actingAs($this->bea)->post('http://a.test/kommentar', ['typ' => 'note', 'id' => $note->id, 'body' => 'Kenne ich gut!'])->assertRedirect();
        $this->actingAs($this->carla)->post('http://a.test/kommentar', ['typ' => 'note', 'id' => $note->id, 'body' => 'Darf nicht'])->assertForbidden();
        Notification::assertSentTo($this->anna, AppNotification::class, fn ($n) => $n->nachricht->titel === 'Bea hat auf deinen Eintrag geantwortet');
        $this->actingAs($this->bea)->get('http://a.test/community')->assertOk()->assertSee('Kenne ich gut!');
    }
}

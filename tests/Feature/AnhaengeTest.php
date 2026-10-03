<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Anhang;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\Resource;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Anhaengen, worum es geht: an Notizen, Aufgaben, Fragen und Nachrichten. */
class AnhaengeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected User $fremd;

    protected Program $hybrid;

    protected Program $selbst;

    protected Event $termin;

    protected Resource $material;

    protected Task $annasAufgabe;

    protected Task $beasAufgabe;

    protected Task $fremdeAufgabe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->fremd = User::factory()->create(['name' => 'Fritz Fremd']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->b->users()->attach($this->fremd, ['role' => Role::Member->value, 'status' => 'active']);

        $this->in(function () {
            $this->hybrid = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid']);
            $this->selbst = Program::create(['slug' => 'anfang', 'title' => 'Der Anfang', 'type' => 'selfpaced']);
            foreach ([$this->hybrid, $this->selbst] as $p) {
                ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
                ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->bea->id]);
            }
            $this->termin = Event::create(['program_id' => $this->hybrid->id, 'title' => 'Call Woche 3', 'starts_at' => now()->addDays(2)]);
            $this->material = Resource::create(['title' => 'Arbeitsblatt Werte', 'url' => 'https://example.com/werte.pdf']);
            $this->material->links()->create(['resourceable_type' => 'program', 'resourceable_id' => $this->hybrid->id]);
            $this->annasAufgabe = Task::create(['user_id' => $this->anna->id, 'title' => 'Brief schreiben']);
            $this->beasAufgabe = Task::create(['user_id' => $this->bea->id, 'title' => 'Beas Geheimnis']);
        });
        $this->fremdeAufgabe = app(CurrentTenant::class)->run($this->b, fn () => Task::create(['user_id' => $this->fremd->id, 'title' => 'Fremde Aufgabe']));
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_notiz_mit_anhaengen_nur_eigenes_und_gemeinsames(): void
    {
        $refs = [
            'task:'.$this->annasAufgabe->id,
            'event:'.$this->termin->id,
            'resource:'.$this->material->id,
            'task:'.$this->beasAufgabe->id,     // fremde, private Aufgabe: nein
            'task:'.$this->fremdeAufgabe->id,   // anderer Mandant: nein
            'unfug:1', 'task:abc',
        ];
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Dazu will ich was sagen', 'refs' => $refs])->assertRedirect();

        $note = $this->in(fn () => Note::where('user_id', $this->anna->id)->first());
        $this->assertSame(['task:'.$this->annasAufgabe->id, 'event:'.$this->termin->id, 'resource:'.$this->material->id], $this->in(fn () => $note->anhangRefs()));
        $this->assertSame(3, $this->in(fn () => Anhang::count()));

        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()
            ->assertSee('Brief schreiben')->assertSee('Call Woche 3')->assertSee('Arbeitsblatt Werte')->assertDontSee('Beas Geheimnis')
            ->assertSee('Etwas anhängen');

        // Bearbeiten ersetzt die Anhaenge, Loeschen raeumt auf
        $this->actingAs($this->anna)->post("http://a.test/notizen/{$note->id}", ['body' => 'Neu', 'refs' => ['event:'.$this->termin->id]])->assertRedirect();
        $this->assertSame(['event:'.$this->termin->id], $this->in(fn () => $note->fresh()->anhangRefs()));
        $this->actingAs($this->anna)->delete("http://a.test/notizen/{$note->id}")->assertRedirect();
        $this->assertSame(0, $this->in(fn () => Anhang::count()));
    }

    public function test_geteilte_aufgabe_darf_die_coachin_anhaengen_die_andere_nicht(): void
    {
        $this->in(fn () => $this->annasAufgabe->update(['visibility' => 'coach']));
        $this->actingAs($this->lea)->post('http://a.test/notizen', ['body' => 'Zu Annas Aufgabe', 'refs' => ['task:'.$this->annasAufgabe->id]])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Anhang::count()));

        $this->actingAs($this->bea)->post('http://a.test/notizen', ['body' => 'Versuch', 'refs' => ['task:'.$this->annasAufgabe->id]])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Anhang::count()));

        // Die Karte fuehrt Anna in ihre Liste, die Coachin ins Dossier von Anna (Reiter Aufgaben), Bea nirgendwohin
        $karte = fn () => app(\App\Support\Anhaenge::class)->karte($this->annasAufgabe->fresh())['url'];
        $this->actingAs($this->anna);
        $this->assertStringEndsWith('/aufgaben#aufgabe-'.$this->annasAufgabe->id, $this->in($karte));
        $this->actingAs($this->lea);
        $m = $this->in(fn () => \App\Models\Membership::where('user_id', $this->anna->id)->first());
        $this->assertStringEndsWith('/coachees/'.$m->id.'?r=aufgaben#aufgabe-'.$this->annasAufgabe->id, $this->in($karte));
        $this->actingAs($this->lea)->get('http://a.test/coachees/'.$m->id.'?r=aufgaben')->assertOk()->assertSee('id="aufgabe-'.$this->annasAufgabe->id.'"', false);
        $this->actingAs($this->bea);
        $this->assertNull($this->in($karte));
    }

    public function test_suche_findet_termine_material_und_lektionen_nicht_fremdes(): void
    {
        $this->in(function () {
            $s = $this->hybrid->steps()->create(['title' => 'Woche 1', 'position' => 1]);
            $this->hybrid->units()->create(['title' => 'Lektion Werte klären', 'step_id' => $s->id, 'position' => 1]);
        });
        $r = $this->actingAs($this->anna)->getJson('http://a.test/anhaenge/suche?q=werte')->assertOk();
        $titel = collect($r->json('karten'))->pluck('titel')->all();
        $this->assertContains('Arbeitsblatt Werte', $titel);
        $this->assertContains('Lektion Werte klären', $titel);
        $this->assertNotContains('Beas Geheimnis', $titel);

        $auswahl = collect($this->actingAs($this->anna)->getJson('http://a.test/anhaenge/suche')->json('karten'));
        $this->assertContains('Call Woche 3', $auswahl->pluck('titel')->all());
        $this->assertContains('Brief schreiben', $auswahl->pluck('titel')->all());
        $this->assertNotContains('Fremde Aufgabe', $auswahl->pluck('titel')->all());

        $this->actingAs($this->fremd)->getJson('http://b.test/anhaenge/suche?q=werte')->assertOk()->assertJson(['karten' => []]);
    }

    public function test_frage_und_nachricht_mit_anhang(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Wie geht das mit dem Brief?', 'refs' => ['task:'.$this->annasAufgabe->id]])->assertRedirect();
        $frage = $this->in(fn () => Question::first());
        $this->assertSame(['task:'.$this->annasAufgabe->id], $this->in(fn () => $frage->anhangRefs()));
        $this->actingAs($this->bea)->get("http://a.test/fragen/{$frage->id}")->assertOk()->assertSee('Brief schreiben');

        $this->actingAs($this->anna)->get('http://a.test/gespraech');
        $conv = $this->in(fn () => Conversation::where('user_id', $this->anna->id)->first());
        $r = $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", ['refs' => ['event:'.$this->termin->id]])->assertOk();
        $this->assertStringContainsString('Call Woche 3', $r->json('html'));
        $m = $this->in(fn () => $conv->messages()->latest('id')->first());
        $this->assertSame('event', $m->ref_type);
        $this->assertSame($this->termin->id, $m->ref_id);

        // Fremdes laesst sich auch im Chat nicht anhaengen: die Nachricht ist dann leer
        $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", ['refs' => ['task:'.$this->beasAufgabe->id]])->assertStatus(422);
    }

    public function test_community_nur_mit_eigenem_raum_waehlbar_selbstlernkurs_vermerkt(): void
    {
        $this->in(function () {
            Question::create(['program_id' => $this->hybrid->id, 'user_id' => $this->anna->id, 'title' => 'Frage im Hybrid']);
            Question::create(['program_id' => $this->selbst->id, 'user_id' => $this->bea->id, 'title' => 'Frage aus dem Anfang']);
        });
        $r = $this->actingAs($this->anna)->get('http://a.test/community')->assertOk()
            ->assertSee('Frage im Hybrid')->assertSee('Frage aus dem Anfang')->assertSee('chip chip-kurs', false);
        // Pillen: nur das Hybrid-Coaching, nicht der Selbstlernkurs
        $this->assertStringContainsString('k='.$this->hybrid->id, $r->getContent());
        $this->assertStringNotContainsString('k='.$this->selbst->id, $r->getContent());

        $this->assertTrue($this->hybrid->gemeinschaft());
        $this->assertFalse($this->selbst->gemeinschaft());
        $this->actingAs($this->anna)->get('http://a.test/kurse/anfang/fragen')->assertOk()->assertSee('In der Community');
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen')->assertOk()->assertSee('Alle im Kurs');

        // "Im Kurs sichtbar" gibt es fuer Notizen nur bei Programmen mit eigenem Raum
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Fuer den Anfang', 'program_id' => $this->selbst->id, 'visibility' => 'program'])->assertRedirect();
        $this->assertSame('coach', $this->in(fn () => Note::first()->visibility));
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Fuer die Gruppe', 'program_id' => $this->hybrid->id, 'visibility' => 'program'])->assertRedirect();
        $this->assertSame('program', $this->in(fn () => Note::latest('id')->first()->visibility));
    }
}

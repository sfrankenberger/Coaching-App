<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Resources\Events\Pages\EditEvent;
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
use App\Observers\EventObserver;
use App\Recordings\Freigabe;
use App\Support\Ics;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['features' => ['zeitleiste' => true, 'projekte' => true]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->anna = User::factory()->create();
        $this->fremd = User::factory()->create();
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->fremd, ['role' => Role::Member->value, 'status' => 'active']);
        $this->program = $this->in(function () {
            $p = Program::create(['slug' => 'kurs', 'title' => 'Kurs A', 'type' => 'hybrid']);
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

    public function test_kursaufgabe_auslassen_und_wieder_aufnehmen(): void
    {
        $t = $this->in(fn () => \App\Models\Task::create(['user_id' => $this->anna->id, 'title' => 'Deine Wochenreflexion', 'kind' => 'reflexion', 'source' => 'program', 'due_at' => now()->subDay()->toDateString()]));
        $this->actingAs($this->anna)->get('http://a.test/aufgaben')->assertOk()->assertSee('Diese Woche keine Reflexion');
        $this->actingAs($this->anna)->post('http://a.test/aufgaben/'.$t->id.'/auslassen')->assertRedirect()->assertSessionHas('meldung');
        $t->refresh();
        $this->assertTrue($t->isSkipped());
        $this->assertFalse($t->isDone());
        $this->assertFalse($t->isOverdue(), 'ausgelassen ist nicht ueberfaellig');
        $this->assertSame(0, $this->in(fn () => \App\Models\Task::where('user_id', $this->anna->id)->open()->count()), 'zaehlt nicht als offen');
        $this->actingAs($this->anna)->get('http://a.test/aufgaben')->assertOk()->assertSee('Ausgelassen')->assertSee('Doch machen');
        $this->actingAs($this->anna)->post('http://a.test/aufgaben/'.$t->id.'/auslassen')->assertRedirect();
        $this->assertTrue($t->fresh()->isOffen());
        // Abhaken hebt das Auslassen auf
        $this->actingAs($this->anna)->post('http://a.test/aufgaben/'.$t->id.'/auslassen')->assertRedirect();
        $this->actingAs($this->anna)->post('http://a.test/aufgaben/'.$t->id.'/haken')->assertRedirect();
        $this->assertTrue($t->fresh()->isDone());
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

        // Aendern: Titel und Sichtbarkeit
        $this->actingAs($this->anna)->post("http://a.test/aufgaben/{$t->id}", ['title' => 'Intention neu', 'visibility' => 'coach'])->assertRedirect('http://a.test/aufgaben');
        $this->assertSame('Intention neu', $t->fresh()->title);
        $this->assertSame('coach', $t->fresh()->visibility);
        $this->actingAs($this->fremd)->post("http://a.test/aufgaben/{$t->id}", ['title' => 'Fremd'])->assertForbidden();

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

        $this->actingAs($this->anna)->get('http://a.test/journal')->assertOk()->assertSee('Aufgaben')->assertSee('Reflexion')->assertSee('Projekte')->assertSee('Viel');
    }

    public function test_weitere_person_am_termin_sieht_termin_kalender_und_aufzeichnung(): void
    {
        $lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $paar = $this->in(fn () => Event::create(['title' => 'Paar-Coaching', 'type' => 'one_on_one', 'user_id' => $this->fremd->id, 'starts_at' => now()->addDays(2), 'recording_url' => 'https://vimeo.com/123456']));

        // Ohne Einladung: Anna sieht die Sitzung von Fremd nicht
        $this->actingAs($this->anna)->get('http://a.test/termine')->assertOk()->assertDontSee('Paar-Coaching');
        $this->actingAs($this->anna)->get('http://a.test/termine/'.$paar->id)->assertForbidden();

        // Das Team haengt Anna an (wie im Coach-Bereich unter "Weitere Personen")
        $this->in(fn () => Livewire::actingAs($lea)->test(EditEvent::class, ['record' => $paar->id])
            ->fillForm(['gaeste' => [$this->anna->id]])->call('save')->assertHasNoFormErrors());
        $this->assertSame([$this->anna->id], $this->in(fn () => $paar->fresh()->gaeste()->pluck('user_id')->all()));

        $this->actingAs($this->anna)->get('http://a.test/termine')->assertOk()->assertSee('Paar-Coaching')->assertSee('2 Personen');
        $this->actingAs($this->anna)->get('http://a.test/termine/'.$paar->id)->assertOk()->assertSee('Dabei:')->assertSee('vimeo.com');
        $this->actingAs($this->fremd)->get('http://a.test/termine/'.$paar->id)->assertOk()->assertSee('Dabei:');

        // Kalender-Abo und Empfaenger (Erinnerungen, Freigabe der Aufzeichnung)
        $token = $this->in(fn () => Ics::tokenFor($this->anna->membershipIn($this->a)));
        $this->get("http://a.test/kalender/{$token}.ics")->assertOk()->assertSee('Paar-Coaching');
        $this->assertEqualsCanonicalizing([$this->fremd->id, $this->anna->id], $this->in(fn () => EventObserver::recipients($paar->fresh())->all()));
        $this->assertEqualsCanonicalizing([$this->fremd->id, $this->anna->id], $this->in(fn () => app(Freigabe::class)->empfaenger($paar->fresh())->all()));

        // Wieder entfernt: weg aus Terminen und Kalender
        $this->in(fn () => $paar->fresh()->gaesteSetzen([]));
        $this->actingAs($this->anna)->get('http://a.test/termine')->assertOk()->assertDontSee('Paar-Coaching');
        $this->get("http://a.test/kalender/{$token}.ics")->assertOk()->assertDontSee('Paar-Coaching');
    }

    public function test_terminliste_zeigt_acht_wochen_und_tagestermine_schmal(): void
    {
        $this->in(function () {
            Event::create(['program_id' => $this->program->id, 'title' => 'Fragentag', 'type' => 'question_day', 'all_day' => true, 'starts_at' => now()->addDays(2)->startOfDay()]);
            Event::create(['program_id' => $this->program->id, 'title' => 'Call bald', 'starts_at' => now()->addDays(3)]);
            Event::create(['program_id' => $this->program->id, 'title' => 'Call in ferner Zukunft', 'starts_at' => now()->addWeeks(12)]);
            // Abgesagte eigene Sitzung bleibt aufrufbar (z. B. aus einem Chat-Anhang), die Seite sagt es
            Event::create(['user_id' => $this->anna->id, 'title' => 'Abgesagte Sitzung', 'type' => 'one_on_one', 'starts_at' => now()->subDays(5), 'is_published' => false, 'cancelled_at' => now()->subDays(6)]);
        });

        $r = $this->actingAs($this->anna)->get('http://a.test/termine')->assertOk();
        $r->assertSee('Fragentag')->assertSee('zeile-tag')->assertSee('Call bald')->assertDontSee('Call in ferner Zukunft')->assertSee('Alle kommenden Termine anzeigen');
        $this->actingAs($this->anna)->get('http://a.test/termine?weit=1')->assertOk()->assertSee('Call in ferner Zukunft')->assertDontSee('Alle kommenden Termine anzeigen');

        $abgesagt = $this->in(fn () => Event::where('title', 'Abgesagte Sitzung')->first());
        $this->actingAs($this->anna)->get("http://a.test/termine/{$abgesagt->id}")->assertOk()->assertSee('Abgesagt');
        $this->actingAs($this->fremd)->get("http://a.test/termine/{$abgesagt->id}")->assertForbidden();

        // Tagestermin: kein "Nicht dabei" und kein "Ich war live dabei"
        $tag = $this->in(fn () => Event::where('title', 'Fragentag')->first());
        $this->actingAs($this->anna)->get("http://a.test/termine/{$tag->id}")->assertOk()->assertDontSee('Nicht dabei')->assertDontSee('live dabei');
    }
}

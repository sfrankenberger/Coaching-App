<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\MediaPosition;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\Reflection;
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

    public function test_wochenaufgabe_mit_art_und_wochentag_knopf_und_speichern_hakt_ab(): void
    {
        [$w1, $w2] = $this->in(function () {
            $w1 = $this->kurs->steps()->first();
            $w2 = $this->kurs->steps()->create(['title' => 'Woche 2', 'position' => 2, 'week_number' => 2, 'unlocks_at' => now()->addWeek()]);

            return [$w1, $w2];
        });
        $t = $this->in(fn () => Task::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'step_id' => $w1->id, 'title' => 'Was hat dich bewegt?', 'kind' => 'notiz', 'weekday' => 3, 'assigned_by' => $this->lea->id, 'source' => 'program', 'visibility' => 'coach']));
        // Wochentag plus Woche ergibt die Faelligkeit: der Mittwoch der Kurswoche
        $this->assertSame(now()->subDay()->startOfWeek()->addDays(2)->toDateString(), $t->due_at->toDateString());

        $r = $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$w1->id}")->assertOk()->assertSee('Notiz schreiben')->assertSee($t->due_at->isToday() ? 'heute dran' : 'Mittwoch');
        $this->assertStringContainsString("/notizen?aufgabe={$t->id}", $r->getContent());

        // Aus der Aufgabe heraus schreiben: die Aufgabe haengt an der Notiz und ist abgehakt
        $this->actingAs($this->anna)->get("http://a.test/notizen?aufgabe={$t->id}")->assertOk()->assertSee('Zur Aufgabe');
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Mich hat bewegt, dass ...', 'aufgabe_id' => $t->id])->assertRedirect('http://a.test/aufgaben');
        $this->assertNotNull($this->in(fn () => $t->fresh()->done_at));
        $this->assertSame(['task:'.$t->id], $this->in(fn () => Note::first()->anhangRefs()));

        // Rueckstand: eine offene Aufgabe aus Woche 1 steht auf der Wochenseite von Woche 2, der Fragentag nicht
        $this->in(function () use ($w1) {
            Task::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'step_id' => $w1->id, 'title' => 'Brief schreiben', 'kind' => 'haken']);
            Task::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'step_id' => $w1->id, 'title' => 'Fragentag nutzen', 'kind' => 'frage']);
        });
        $this->actingAs($this->lea)->get("http://a.test/kurse/hybrid/schritt/{$w2->id}")->assertOk();
        $this->travel(8)->days();
        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$w2->id}")->assertOk()
            ->assertSee('Aus früheren Wochen noch offen: 2')->assertSee('Brief schreiben')->assertSee('Deine Wochenreflexion')->assertDontSee('Fragentag nutzen');
        $this->travelBack();
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

    public function test_wochenband_reflexion_und_fragen_der_woche(): void
    {
        $w1 = $this->in(fn () => $this->kurs->steps()->first());
        $w2 = $this->in(fn () => $this->kurs->steps()->create(['title' => 'Woche 2', 'position' => 2, 'week_number' => 2, 'unlocks_at' => now()->addDays(6)]));

        // Band: Woche 1 ist "Jetzt" und hier, Woche 2 gesperrt; kein Sprungknopf, weil wir auf der aktuellen Woche sind
        $seite = $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$w1->id}")->assertOk();
        $seite->assertSee('wochenband')->assertSee('Jetzt')->assertSee('wb zu')->assertDontSee('Zur aktuellen Woche')->assertSee('Reflexion schreiben')->assertDontSee('Deine Fragen diese Woche');

        // Reflexion und Frage in dieser Woche: Stand "geschrieben", eigene Fragen unter der Woche
        $this->in(function () {
            Reflection::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'week_label' => 'Woche 40', 'went_well' => 'Der Morgen am Fenster']);
            Question::create(['user_id' => $this->anna->id, 'program_id' => $this->kurs->id, 'title' => 'Wann atme ich?']);
        });
        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$w1->id}")->assertOk()
            ->assertSee('Deine Wochenreflexion')->assertSee('Deine Reflexion dieser Woche')->assertSee('Der Morgen am Fenster')
            ->assertSee('Deine Fragen diese Woche')->assertSee('Wann atme ich?');
        // Reflexion und Frage da: die Aufgaben dazu sind abgehakt
        $this->in(fn () => $this->assertSame(1, Task::where('user_id', $this->anna->id)->where('step_id', $w1->id)->where('kind', 'reflexion')->whereNotNull('done_at')->count(), 'Reflexionstag als erledigte Aufgabe'));

        // Lea sieht Woche 2 schon, mit Sprung zur aktuellen Woche
        $this->actingAs($this->lea)->get("http://a.test/kurse/hybrid/schritt/{$w2->id}")->assertOk()->assertSee('Zur aktuellen Woche');
        // Startseite: der Call steht in der Wochenkarte
        $this->in(fn () => $this->anna->membershipIn()->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save());
        $call = $this->in(fn () => Event::where('title', 'Gruppencall Woche 1')->first());
        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertSee('Diese Woche im Kurs')->assertSee('Call '.$call->starts_at->translatedFormat('D, j. M, H:i').' Uhr');
    }

    public function test_stelle_je_video_der_playlist_und_material_angeschaut(): void
    {
        $u = $this->in(fn () => $this->kurs->units()->first());
        // Zweites Video der Playlist hat einen eigenen Schluessel, Unsinn wird abgelehnt
        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => "unit-{$u->id}-1", 'seconds' => 90, 'duration' => 600])->assertOk();
        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => "unit-{$u->id}-100", 'seconds' => 1])->assertStatus(422);
        $this->assertSame(90, $this->in(fn () => MediaPosition::where('key', "unit-{$u->id}-1")->value('seconds')));

        // Material: Stelle wird wieder aufgenommen, ab 80 Prozent angeschaut, Knopf schaltet um
        $r = $this->in(function () {
            $r = Resource::create(['title' => 'Impulsvideo', 'type' => 'video', 'url' => 'https://vimeo.com/555']);
            Resourceable::create(['resource_id' => $r->id, 'resourceable_type' => 'program', 'resourceable_id' => $this->kurs->id]);

            return $r;
        });
        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => "resource-{$r->id}", 'seconds' => 100, 'duration' => 1000])->assertOk()->assertJsonPath('erledigt', false);
        $this->actingAs($this->anna)->get("http://a.test/material/{$r->id}")->assertOk()->assertSee('Du warst bei 01:40')->assertSee('data-start="100"', false)->assertSee('Als angeschaut markieren');
        $this->actingAs($this->anna)->postJson('http://a.test/medien/position', ['key' => "resource-{$r->id}", 'seconds' => 850, 'duration' => 1000])->assertOk()->assertJsonPath('erledigt', true);
        $this->actingAs($this->anna)->get("http://a.test/material/{$r->id}")->assertOk()->assertSee('Angeschaut')->assertSee('Nochmal ansehen')->assertDontSee('Du warst bei');
        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$this->in(fn () => $this->kurs->steps()->first())->id}")->assertOk();
        $this->actingAs($this->anna)->post("http://a.test/material/{$r->id}/gesehen")->assertRedirect();
        $this->assertNull($this->in(fn () => MediaPosition::where('key', "resource-{$r->id}")->first()->watched_at));
        $this->assertSame(0, $this->in(fn () => MediaPosition::where('key', "resource-{$r->id}")->value('seconds')));
    }

    public function test_reflexion_und_frage_der_woche_fuer_alle_im_kurs_auch_ohne_besuch(): void
    {
        $w1 = $this->in(fn () => $this->kurs->steps()->first());
        $this->in(fn () => Event::create(['program_id' => $this->kurs->id, 'step_id' => $w1->id, 'title' => 'Fragentag', 'type' => 'question_day', 'all_day' => true, 'starts_at' => now()->addDays(2)]));
        $bea = User::factory()->create();
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->in(fn () => ProgramMember::create(['program_id' => $this->kurs->id, 'user_id' => $bea->id]));
        $this->in(fn () => ProgramMember::create(['program_id' => $this->kurs->id, 'user_id' => $this->lea->id]));

        // Lauf beim Wochenstart: alle Teilnehmerinnen, nicht das Team, beim zweiten Mal nichts Neues
        $this->artisan('wochenaufgaben:anlegen', ['tenant' => 'a'])->expectsOutputToContain('Hybrid: 4 neu')->assertSuccessful();
        $this->in(function () use ($bea, $w1) {
            $this->assertSame(['frage', 'reflexion'], Task::where('user_id', $this->anna->id)->orderBy('kind')->pluck('kind')->all());
            $this->assertSame(2, Task::where('user_id', $bea->id)->count());
            $this->assertSame(0, Task::where('user_id', $this->lea->id)->count(), 'Team bekommt im Lauf nichts');
            $t = Task::where('user_id', $this->anna->id)->where('kind', 'reflexion')->first();
            $this->assertSame($w1->id, $t->step_id);
            $this->assertSame('program', $t->source);
            $this->assertSame((int) now()->addDays(3)->isoWeekday(), $t->weekday);
        });
        $this->artisan('wochenaufgaben:anlegen', ['tenant' => 'a'])->doesntExpectOutputToContain('neu')->assertSuccessful();

        // Abhaken ohne etwas geschrieben zu haben, direkt an der Karte unter Meine Aufgaben
        $t = $this->in(fn () => Task::where('user_id', $this->anna->id)->where('kind', 'reflexion')->first());
        $this->actingAs($this->anna)->get('https://a.test/aufgaben')->assertOk()->assertSee('Deine Wochenreflexion')->assertSee('Deine Frage für den Fragentag');
        $this->actingAs($this->anna)->post('https://a.test/aufgaben/'.$t->id.'/haken')->assertRedirect();
        $this->assertNotNull($this->in(fn () => $t->fresh()->done_at));

        // Neu dazugekommen, noch kein Lauf: Meine Aufgaben legt die Woche beim Oeffnen an
        $cara = User::factory()->create();
        $this->a->users()->attach($cara, ['role' => Role::Member->value, 'status' => 'active']);
        $this->in(fn () => ProgramMember::create(['program_id' => $this->kurs->id, 'user_id' => $cara->id]));
        $this->actingAs($cara)->get('https://a.test/aufgaben')->assertOk()->assertSee('Deine Wochenreflexion');
        $this->assertSame(2, $this->in(fn () => Task::where('user_id', $cara->id)->count()));

        // Team: im Arbeitsplatz nichts, in der Teilnehmer-Ansicht wie eine Teilnehmerin
        $this->actingAs($this->lea)->get('https://a.test/aufgaben')->assertOk();
        $this->assertSame(0, $this->in(fn () => Task::where('user_id', $this->lea->id)->count()));
        $this->in(fn () => \App\Coach\Ansicht::setzen($this->lea, 'teilnehmer'));
        $this->actingAs($this->lea)->get('https://a.test/')->assertOk()->assertSee('Deine Wochenreflexion');
        $this->assertSame(2, $this->in(fn () => Task::where('user_id', $this->lea->id)->count()));
        // Auf der Wochenseite nur die Aufgaben, nicht zusaetzlich die Tageszeilen (die sind fuer den Arbeitsplatz)
        $this->actingAs($this->lea)->get('https://a.test/kurse/hybrid/schritt/'.$w1->id)->assertOk()->assertSee('Deine Wochenreflexion')->assertDontSee('Reflexion schreiben</b>', false);
    }
}

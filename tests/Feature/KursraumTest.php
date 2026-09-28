<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Answer;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Progress;
use App\Models\Question;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KursraumTest extends TestCase
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

        $this->program = app(CurrentTenant::class)->run($this->a, function () {
            $p = Program::create(['slug' => 'testkurs', 'title' => 'Testkurs', 'type' => 'hybrid', 'pacing' => 'weekly']);
            $w1 = $p->steps()->create(['title' => 'Woche 1', 'position' => 1, 'week_number' => 1, 'unlocks_at' => now()->subDay()]);
            $w2 = $p->steps()->create(['title' => 'Woche 2', 'position' => 2, 'week_number' => 2, 'unlocks_at' => now()->addWeek()]);
            $u1 = $p->units()->create(['title' => 'Willkommen', 'step_id' => $w1->id, 'position' => 1, 'videos' => [['url' => 'https://vimeo.com/123456', 'title' => 'Intro']]]);
            $u2 = $p->units()->create(['title' => 'Deine Intention', 'step_id' => $w1->id, 'position' => 2, 'type' => 'exercise_set']);
            $u2->exercises()->create(['type' => 'text', 'prompt' => 'Was soll anders sein?', 'position' => 1]);
            $u2->exercises()->create(['type' => 'scale', 'prompt' => 'Zufriedenheit', 'position' => 2]);
            $p->units()->create(['title' => 'Noch zu', 'step_id' => $w2->id, 'position' => 1]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_meine_kurse_und_uebersicht(): void
    {
        $this->actingAs($this->anna)->get('http://a.test/kurse')->assertOk()->assertSee('Testkurs')->assertSee('0 von 3');
        $this->actingAs($this->fremd)->get('http://a.test/kurse')->assertOk()->assertDontSee('Testkurs');

        $this->actingAs($this->anna)->get('http://a.test/kurse/testkurs')->assertOk()
            ->assertSee('Woche 1')->assertSee('Woche 2')->assertSee('Jetzt dran');
        $this->actingAs($this->fremd)->get('http://a.test/kurse/testkurs')->assertForbidden();
    }

    public function test_schritt_noch_zu(): void
    {
        [$w1, $w2] = $this->in(fn () => $this->program->steps()->get());
        $this->actingAs($this->anna)->get("http://a.test/kurse/testkurs/schritt/{$w1->id}")->assertOk()->assertSee('Willkommen')->assertSee('Deine Intention');
        $this->actingAs($this->anna)->get("http://a.test/kurse/testkurs/schritt/{$w2->id}")->assertForbidden();

        $zu = $this->in(fn () => $this->program->units()->where('title', 'Noch zu')->first());
        $this->actingAs($this->anna)->get("http://a.test/kurse/testkurs/einheit/{$zu->id}")->assertForbidden();
    }

    public function test_einheit_mit_video_antworten_notiz_und_erledigt(): void
    {
        $unit = $this->in(fn () => $this->program->units()->where('title', 'Willkommen')->first());
        $this->actingAs($this->anna)->get("http://a.test/kurse/testkurs/einheit/{$unit->id}")->assertOk()
            ->assertSee('player.vimeo.com/video/123456')->assertSee('Als erledigt markieren');

        $this->actingAs($this->anna)->postJson("http://a.test/kurse/testkurs/einheit/{$unit->id}/erledigt")->assertOk()->assertJsonPath('erledigt', true)->assertJsonPath('stand.done', 1);
        $this->assertSame(1, app(CurrentTenant::class)->run($this->a, fn () => Progress::whereNotNull('completed_at')->count()));

        $uebung = $this->in(fn () => $this->program->units()->where('title', 'Deine Intention')->first());
        $frage = app(CurrentTenant::class)->run($this->a, fn () => $uebung->exercises()->where('type', 'text')->first());
        $skala = app(CurrentTenant::class)->run($this->a, fn () => $uebung->exercises()->where('type', 'scale')->first());

        $this->actingAs($this->anna)->postJson('http://a.test/kurse/antwort', ['exercise_id' => $frage->id, 'value' => 'Mehr Ruhe'])->assertOk();
        $this->actingAs($this->anna)->postJson('http://a.test/kurse/antwort', ['exercise_id' => $skala->id, 'value' => 7])->assertOk();
        $this->actingAs($this->fremd)->postJson('http://a.test/kurse/antwort', ['exercise_id' => $frage->id, 'value' => 'Hack'])->assertForbidden();

        $antworten = app(CurrentTenant::class)->run($this->a, fn () => Answer::where('user_id', $this->anna->id)->get()->keyBy('exercise_id'));
        $this->assertSame('Mehr Ruhe', $antworten[$frage->id]->value['v']);
        $this->assertSame(7, $antworten[$skala->id]->value['v']);
        $this->assertFalse($antworten[$frage->id]->shared_with_coach);

        $this->actingAs($this->anna)->get("http://a.test/kurse/testkurs/einheit/{$uebung->id}")->assertOk()->assertSee('Mehr Ruhe')->assertSee('data-uebung-voll>2</span> von 2', false);

        $this->actingAs($this->anna)->postJson("http://a.test/kurse/testkurs/einheit/{$uebung->id}/teilen")->assertOk()->assertJsonPath('geteilt', true);
        $this->assertTrue($antworten[$frage->id]->fresh()->shared_with_coach);

        $this->actingAs($this->anna)->postJson("http://a.test/kurse/testkurs/einheit/{$unit->id}/notiz", ['body' => 'Merken!'])->assertOk();
        $this->assertSame('Merken!', app(CurrentTenant::class)->run($this->a, fn () => Note::where('user_id', $this->anna->id)->first()?->body));
    }

    public function test_freigabe_einmal_am_anfang_beim_arbeitsbuch(): void
    {
        app(CurrentTenant::class)->run($this->a, fn () => $this->program->update(['type' => 'workbook']));

        $this->actingAs($this->anna)->get('http://a.test/kurse/testkurs')->assertOk()->assertSee('Wer liest mit?');
        $this->actingAs($this->anna)->post('http://a.test/kurse/testkurs/freigabe', ['modus' => 'alles'])->assertRedirect();
        $this->actingAs($this->anna)->get('http://a.test/kurse/testkurs')->assertOk()->assertDontSee('Wer liest mit?');

        $frage = $this->in(fn () => $this->program->units()->where('title', 'Deine Intention')->first()->exercises()->first());
        $this->actingAs($this->anna)->postJson('http://a.test/kurse/antwort', ['exercise_id' => $frage->id, 'value' => 'geteilt'])->assertOk();
        $this->assertTrue(app(CurrentTenant::class)->run($this->a, fn () => Answer::where('user_id', $this->anna->id)->first()->shared_with_coach));
    }

    public function test_selbstlernkurs_teilt_nichts_und_1_zu_1_ist_kein_kurs(): void
    {
        [$selbst, $u, $einzel] = $this->in(function () {
            $selbst = Program::create(['slug' => 'selbst', 'title' => 'Selbstlernkurs Ruhe', 'type' => 'selfpaced']);
            $u = $selbst->units()->create(['title' => 'Übung', 'position' => 1, 'type' => 'exercise_set']);
            $u->exercises()->create(['type' => 'text', 'prompt' => 'Was nimmst du mit?', 'position' => 1]);
            ProgramMember::create(['program_id' => $selbst->id, 'user_id' => $this->anna->id]);
            $einzel = Program::create(['slug' => 'einzel', 'title' => 'Einzelbegleitung Lea', 'type' => 'one_on_one', 'settings' => ['sitzungen_gesamt' => 5]]);
            ProgramMember::create(['program_id' => $einzel->id, 'user_id' => $this->anna->id]);

            return [$selbst, $u, $einzel];
        });

        // 1:1 gehoert nicht unter Kurse, weder auf der Seite noch im Menue
        $this->actingAs($this->anna)->get('http://a.test/kurse')->assertOk()->assertSee('Testkurs')->assertSee('Selbstlernkurs Ruhe')->assertDontSee('Einzelbegleitung Lea')->assertDontSee('Begleitungen');
        // Selbstlernkurs: kein Teilen-Knopf, Teilen abgelehnt, Testkurs (Hybrid) teilt weiter
        $this->actingAs($this->anna)->get("http://a.test/kurse/selbst/einheit/{$u->id}")->assertOk()->assertSee('Was nimmst du mit?')->assertDontSee('Mit deiner Coachin teilen');
        $this->actingAs($this->anna)->postJson("http://a.test/kurse/selbst/einheit/{$u->id}/teilen")->assertForbidden();
        $this->actingAs($this->anna)->post('http://a.test/kurse/selbst/freigabe', ['modus' => 'alles'])->assertForbidden();
        $this->assertTrue($this->program->teilbar());
        $this->assertFalse($selbst->teilbar());
        $this->assertTrue($einzel->teilbar());
        // Lea kann es je Kurs einschalten
        $this->in(fn () => $selbst->forceFill(['settings' => ['teilen' => '1']])->save());
        $this->assertTrue($selbst->fresh()->teilbar());
        $this->actingAs($this->anna)->get("http://a.test/kurse/selbst/einheit/{$u->id}")->assertOk()->assertSee('Mit deiner Coachin teilen');
    }

    public function test_community_nur_gruppenkurse_und_wer_ist_dabei(): void
    {
        $this->anna->forceFill(['name' => 'Anna Muster'])->save();
        $this->fremd->forceFill(['name' => 'Fremd Person'])->save();
        $lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->in(function () {
            $einzel = Program::create(['slug' => 'einzel', 'title' => 'Einzelbegleitung Anna', 'type' => 'one_on_one']);
            ProgramMember::create(['program_id' => $einzel->id, 'user_id' => $this->anna->id]);
            $selbst = Program::create(['slug' => 'selbst', 'title' => 'Selbstlernkurs Ruhe', 'type' => 'selfpaced']);
            ProgramMember::create(['program_id' => $selbst->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $selbst->id, 'user_id' => $this->fremd->id]);
            Question::create(['program_id' => $this->program->id, 'user_id' => $this->anna->id, 'title' => 'Frage im Testkurs']);
            Question::create(['program_id' => $selbst->id, 'user_id' => $this->fremd->id, 'title' => 'Frage im Selbstlernkurs']);
        });
        $this->actingAs($this->anna)->get('http://a.test/community')->assertOk()
            ->assertSee('Frage im Testkurs')->assertSee('Frage im Selbstlernkurs')->assertSee('Wer ist dabei')->assertDontSee('Einzelbegleitung');
        $this->actingAs($this->anna)->get('http://a.test/community?k='.$this->program->id)->assertOk()->assertSee('Frage im Testkurs')->assertDontSee('Frage im Selbstlernkurs');
        // Wer ist dabei: Team immer, andere nur mit Freigabe
        $this->actingAs($this->anna)->get('http://a.test/community/wer-ist-dabei')->assertOk()->assertSee('Lea')->assertSee('Coach')->assertDontSee('Fremd Person')->assertSee('noch nicht sichtbar');
        $this->actingAs($this->fremd)->post('http://a.test/profil', ['name' => 'Fremd Person', 'community_sichtbar' => 1, 'ueber_mich' => 'Ich coache Mütter.'])->assertRedirect();
        $this->actingAs($this->anna)->get('http://a.test/community/wer-ist-dabei')->assertOk()->assertSee('Fremd Person')->assertSee('Ich coache Mütter.')->assertSee('Selbstlernkurs Ruhe');
        $this->actingAs($this->anna)->get('http://a.test/community/wer-ist-dabei?k='.$this->program->id)->assertOk()->assertDontSee('Fremd Person');
    }
}

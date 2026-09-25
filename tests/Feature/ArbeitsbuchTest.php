<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\ConvertAnswerAudio;
use App\Models\Answer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArbeitsbuchTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected User $bea;

    protected array $ex = [];

    protected Unit $u1;

    protected Unit $u2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->anna = User::factory()->create();
        $this->bea = User::factory()->create();
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);

        $this->in(function () {
            $p = Program::create(['slug' => 'brief', 'title' => 'Liebesbrief', 'type' => 'workbook']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id, 'share_mode' => 'einzeln']);
            $this->u1 = $p->units()->create(['title' => 'Sammeln', 'type' => 'exercise_set', 'position' => 1]);
            $this->u2 = $p->units()->create(['title' => 'Lesen', 'type' => 'exercise_set', 'position' => 2]);
            $this->ex['liste'] = $this->u1->exercises()->create(['type' => 'list', 'prompt' => 'Was du dir wuenschst', 'position' => 1, 'options' => ['platzhalter' => 'Ich wuensche mir ...']]);
            $this->ex['paare'] = $this->u1->exercises()->create(['type' => 'pairs', 'position' => 2, 'options' => ['links' => 'Der Gedanke', 'rechts' => 'Umgedreht']]);
            $this->ex['brief'] = $this->u1->exercises()->create(['type' => 'letter', 'prompt' => 'Dein Brief', 'position' => 3, 'options' => ['zeilen' => 18]]);
            $this->ex['spiegel'] = $this->u2->exercises()->create(['type' => 'mirror', 'prompt' => 'Das hast du dir gewuenscht', 'position' => 1, 'options' => ['exercise_id' => $this->ex['liste']->id]]);
            $this->ex['ton'] = $this->u2->exercises()->create(['type' => 'audio', 'prompt' => 'Deine Aufnahme', 'position' => 2, 'options' => ['exercise_id' => $this->ex['brief']->id]]);
            $this->ex['mit'] = $this->u2->exercises()->create(['type' => 'takeaway', 'prompt' => 'Nimm ihn mit', 'position' => 3]);
            $this->ex['praxis'] = $this->u2->exercises()->create(['type' => 'practice', 'position' => 4, 'options' => ['tage' => 21, 'aufgabe' => 'Brief laut lesen']]);
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_liste_paare_brief_spiegel_und_mitnehmen(): void
    {
        $antwort = fn ($ex, $v) => $this->actingAs($this->anna)->postJson('http://a.test/kurse/antwort', ['exercise_id' => $ex->id, 'value' => $v]);
        $antwort($this->ex['liste'], ['Mehr Ruhe', '  ', 'Mehr Mut'])->assertOk();
        $antwort($this->ex['paare'], [['Ich bin zu laut', 'ich bin lebendig'], ['', '']])->assertOk();
        $antwort($this->ex['brief'], "Liebe Anna,\ndu darfst.")->assertOk();

        $this->in(function () {
            $this->assertSame(['Mehr Ruhe', 'Mehr Mut'], Answer::where('exercise_id', $this->ex['liste']->id)->first()->value['v']);
            $this->assertSame([['Ich bin zu laut', 'ich bin lebendig']], Answer::where('exercise_id', $this->ex['paare']->id)->first()->value['v']);
        });

        $this->actingAs($this->anna)->get("http://a.test/kurse/brief/einheit/{$this->u1->id}")->assertOk()
            ->assertSee('value="Mehr Mut"', false)->assertSee('Umgedreht')->assertSee('rows="18"', false);
        $this->actingAs($this->anna)->get("http://a.test/kurse/brief/einheit/{$this->u2->id}")->assertOk()
            ->assertSee('Das hast du dir gewuenscht')->assertSee('Mehr Ruhe')->assertSee('Dein Text zum Ablesen')->assertSee('du darfst.')
            ->assertSee('Text kopieren')->assertSee('Ich bin zu laut → ich bin lebendig')->assertSee('Praxis starten');

        // Bea hat nichts geschrieben: Spiegel ist leer, Annas Antworten tauchen nicht auf
        $this->in(fn () => ProgramMember::create(['program_id' => $this->u1->program_id, 'user_id' => $this->bea->id]));
        $this->actingAs($this->bea)->get("http://a.test/kurse/brief/einheit/{$this->u2->id}")->assertOk()->assertDontSee('Mehr Ruhe')->assertSee('Hier steht noch nichts.');
    }

    public function test_praxis_und_aufnahme(): void
    {
        Queue::fake();
        $this->actingAs($this->anna)->post("http://a.test/kurse/uebung/{$this->ex['praxis']->id}/praxis")->assertRedirect();
        $this->actingAs($this->anna)->post("http://a.test/kurse/uebung/{$this->ex['praxis']->id}/praxis");
        $this->in(function () {
            $t = Task::where('user_id', $this->anna->id)->get();
            $this->assertCount(1, $t, 'nur einmal starten');
            $this->assertTrue($t->first()->is_daily);
            $this->assertSame('Brief laut lesen', $t->first()->title);
        });
        $this->actingAs($this->anna)->get("http://a.test/kurse/brief/einheit/{$this->u2->id}")->assertSee('Tag 1 von 21');

        $this->actingAs($this->anna)->post('http://a.test/kurse/antwort/aufnahme', ['exercise_id' => $this->ex['ton']->id, 'ton' => UploadedFile::fake()->create('aufnahme.webm', 50, 'audio/webm')])
            ->assertOk()->assertJsonStructure(['url']);
        $a = $this->in(fn () => Answer::where('exercise_id', $this->ex['ton']->id)->first());
        $this->assertStringStartsWith("tenants/{$this->a->id}/answers/{$this->anna->id}/", $a->value['v']);
        Storage::disk('local')->assertExists($a->value['v']);
        Queue::assertPushed(ConvertAnswerAudio::class);

        $this->actingAs($this->anna)->get("http://a.test/kurse/antwort/{$a->id}/aufnahme")->assertOk();
        $this->actingAs($this->bea)->get("http://a.test/kurse/antwort/{$a->id}/aufnahme")->assertForbidden();
        // Ueber den normalen Antwortweg laesst sich keine Aufnahme setzen
        $this->actingAs($this->anna)->postJson('http://a.test/kurse/antwort', ['exercise_id' => $this->ex['ton']->id, 'value' => '/etc/passwd'])->assertStatus(422);
    }
}

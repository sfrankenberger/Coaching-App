<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Enums\Role;
use App\Models\Message;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Reflection;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Block C: Chat-Feinheiten, Reflexion mit Kurswoche und Rueckblick, Foto und Link an Notizen, Arbeitsbuch-Freigabe aendern. */
class BlockCTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_chat_neu_trenner_doppelsendeschutz_und_team_liste(): void
    {
        $conv = $this->in(fn () => app(Chat::class)->directFor($this->anna));
        // Anna liest, dann schreibt Lea zweimal: beim naechsten Besuch steht der Trenner vor der ersten neuen Nachricht
        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertDontSee('neu-trenner');
        $this->actingAs($this->lea)->post("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Hallo Anna'])->assertRedirect();
        $this->actingAs($this->lea)->post("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Hallo Anna'])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Message::where('conversation_id', $conv->id)->count()), 'Doppelklick schickt nicht zweimal');
        $this->travel(10)->seconds();
        $this->actingAs($this->lea)->post("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Hallo Anna'])->assertRedirect();
        $this->assertSame(2, $this->in(fn () => Message::where('conversation_id', $conv->id)->count()));

        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('neu-trenner')->assertSee('blase-neu');
        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertDontSee('neu-trenner');

        // Team-Liste: Anna antwortet, dann "wartet" mit Textvorschau; nach Leas Antwort und Annas Lesen "gelesen"
        $this->travel(5)->seconds();
        $this->actingAs($this->anna)->post("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Mir geht es gut, danke der Nachfrage'])->assertRedirect();
        $this->actingAs($this->lea)->get('http://a.test/gespraech')->assertOk()->assertSee('wartet')->assertSee('Mir geht es gut');
        $this->assertSame(1, $this->in(fn () => app(Chat::class)->unreadFor($this->lea)), 'eine Person wartet');
        $this->travel(10)->seconds();
        $this->actingAs($this->lea)->post("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Schön zu hören'])->assertRedirect();
        $this->actingAs($this->lea)->get('http://a.test/gespraech')->assertOk()->assertDontSee('>wartet<', false)->assertDontSee('>gelesen<', false);
        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk();
        $this->actingAs($this->lea)->get('http://a.test/gespraech')->assertOk()->assertSee('gelesen')->assertSee('Du: Schön zu hören');
    }

    public function test_reflexion_mit_kurswoche_und_rueckblick(): void
    {
        [$p, $w1] = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'type' => 'hybrid', 'pacing' => 'weekly']);
            $w1 = $p->steps()->create(['title' => 'Ankommen', 'position' => 1, 'week_number' => 1, 'unlocks_at' => now()->subDay()]);
            $p->steps()->create(['title' => 'Später', 'position' => 2, 'week_number' => 2, 'unlocks_at' => now()->addDays(6)]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return [$p, $w1];
        });
        $this->in(fn () => Reflection::create(['user_id' => $this->anna->id, 'week_label' => 'Woche 38', 'focus' => 'Jeden Morgen zehn Minuten atmen', 'created_at' => now()->subDays(8)]));

        $seite = $this->actingAs($this->anna)->get('http://a.test/reflexion')->assertOk();
        $seite->assertSee('Was hattest du dir vorgenommen?')->assertSee('Jeden Morgen zehn Minuten atmen')->assertSee('Woche 1: Ankommen')->assertDontSee('Woche 2: Später');

        $this->actingAs($this->anna)->post('http://a.test/reflexion', ['went_well' => 'Das Atmen', 'step_id' => $w1->id])->assertRedirect();
        $r = $this->in(fn () => Reflection::latest('id')->first());
        $this->assertSame($w1->id, $r->step_id);
        $this->assertSame($p->id, $r->program_id);
        // Auf der Wochenseite steht sie als Reflexion dieser Woche
        $this->actingAs($this->anna)->get("http://a.test/kurse/hybrid/schritt/{$w1->id}")->assertOk()->assertSee('Deine Reflexion dieser Woche')->assertSee('Das Atmen');
    }

    public function test_notiz_mit_foto_und_link(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/notizen', ['body' => 'Mein Handschriftzettel', 'link_url' => 'https://example.ch/artikel', 'bild' => UploadedFile::fake()->image('zettel.jpg', 400, 300)])->assertRedirect();
        $n = $this->in(fn () => Note::first());
        $this->assertNotNull($n->image_path);
        $this->assertSame('https://example.ch/artikel', $n->link_url);
        Storage::disk('local')->assertExists($n->image_path);

        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()->assertSee("/notizen/{$n->id}/foto")->assertSee('example.ch/artikel');
        $this->actingAs($this->anna)->get("http://a.test/notizen/{$n->id}/foto")->assertOk();
        $this->actingAs($this->lea)->get("http://a.test/notizen/{$n->id}/foto")->assertOk();
        $bea = User::factory()->create();
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->actingAs($bea)->get("http://a.test/notizen/{$n->id}/foto")->assertForbidden();

        // Foto entfernen
        $this->actingAs($this->anna)->post("http://a.test/notizen/{$n->id}", ['body' => 'Mein Handschriftzettel', 'bild_weg' => 1])->assertRedirect();
        $this->assertNull($n->fresh()->image_path);
    }

    public function test_arbeitsbuch_freigabe_laesst_sich_aendern(): void
    {
        $p = $this->in(function () {
            $p = Program::create(['slug' => 'buch', 'title' => 'Arbeitsbuch', 'type' => 'workbook', 'pacing' => 'self_paced', 'settings' => ['teilen' => '1']]);
            $p->units()->create(['title' => 'Eins', 'position' => 1]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id, 'share_mode' => 'einzeln']);

            return $p;
        });
        $this->actingAs($this->anna)->get('http://a.test/kurse/buch')->assertOk()->assertSee('du entscheidest je Übung');
        $this->actingAs($this->anna)->post('http://a.test/kurse/buch/freigabe', ['modus' => 'alles'])->assertRedirect();
        $this->assertSame('alles', $this->in(fn () => ProgramMember::where('program_id', $p->id)->first()->share_mode));
        $this->actingAs($this->anna)->get('http://a.test/kurse/buch')->assertOk()->assertSee('alles ist mit deiner Coachin geteilt');
    }
}

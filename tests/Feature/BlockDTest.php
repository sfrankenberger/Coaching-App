<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Content\PodcastAufbereiten;
use App\Enums\Role;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Resource;
use App\Models\Resourceable;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Block D: Podcast aufbereiten, Nachbarn, Neuigkeiten fuer mehrere Kurse mit Gelesen-Zustand, Ressourcen-Regal. */
class BlockDTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected Program $hybrid;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['audio' => ['anbieter' => 'assemblyai', 'assemblyai_key' => 'aai']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'joined_at' => now()->subMonth()]);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active', 'joined_at' => now()->subMonth()]);
        foreach ([$this->anna, $this->bea] as $u) {
            $u->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        }
        $this->hybrid = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'type' => 'hybrid', 'pacing' => 'weekly', 'is_published' => true]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_podcastfolge_bekommt_abschrift_mit_zeitmarken_und_kapitel(): void
    {
        Http::fake([
            'api.assemblyai.com/v2/transcript' => Http::response(['id' => 'tr1']),
            'api.assemblyai.com/v2/transcript/tr1/sentences' => Http::response(['sentences' => [['start' => 2000, 'text' => 'Hallo und willkommen.'], ['start' => 5000, 'text' => 'Heute geht es um Ruhe.'], ['start' => 40000, 'text' => 'Zweiter Teil.']]]),
            'api.assemblyai.com/v2/transcript/tr1' => Http::response(['status' => 'completed', 'text' => 'Hallo und willkommen. Heute geht es um Ruhe. Zweiter Teil.']),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['zusammenfassung' => 'Es geht um Ruhe.', 'kapitel' => [['start' => 0, 'titel' => 'Willkommen'], ['start' => 40, 'titel' => 'Zweiter Teil']], 'faq' => [['frage' => 'Warum Ruhe?', 'antwort' => 'Weil.']], 'schlagworte' => ['Ruhe']])]], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]),
        ]);
        [$e1, $e2] = $this->in(fn () => [
            PodcastEpisode::create(['show' => 'Abenteuer', 'guid' => 'g1', 'title' => 'Folge eins', 'slug' => 'folge-eins', 'audio_url' => 'https://pod.test/1.mp3', 'is_published' => true, 'published_at' => now()->subDays(10)]),
            PodcastEpisode::create(['show' => 'Abenteuer', 'guid' => 'g2', 'title' => 'Folge zwei', 'slug' => 'folge-zwei', 'audio_url' => 'https://pod.test/2.mp3', 'is_published' => true, 'published_at' => now()->subDays(3), 'transcript' => 'schon da', 'chapters' => [['start' => 0, 'titel' => 'Start']]]),
        ]);
        $this->assertSame(['abschriften' => 1, 'kapitel' => 1, 'offen' => 0], $this->in(fn () => app(PodcastAufbereiten::class)->lauf()));
        $e1 = $e1->fresh();
        $this->assertStringContainsString('[00:02] Hallo und willkommen. Heute geht es um Ruhe.', $e1->transcript);
        $this->assertStringContainsString('[00:40] Zweiter Teil.', $e1->transcript);
        $this->assertSame('Zweiter Teil', $e1->chapters[1]['titel']);
        $this->assertSame('Es geht um Ruhe.', $e1->summary);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/transcript') && $r['audio_url'] === 'https://pod.test/1.mp3');
        $this->assertSame(['abschriften' => 0, 'kapitel' => 0, 'offen' => 0], $this->in(fn () => app(PodcastAufbereiten::class)->lauf()), 'nichts mehr offen');
        $this->artisan('podcast:aufbereiten', ['tenant' => 'a'])->assertSuccessful();

        // Nachbarn auf der Folgenseite
        $this->actingAs($this->anna)->get("http://a.test/impulse/folge/{$e1->id}")->assertOk()->assertSee('Zweiter Teil')->assertSee('Folge zwei')->assertSee('data-springe="40"', false);
        $this->actingAs($this->anna)->get("http://a.test/impulse/folge/{$e2->id}")->assertOk()->assertSee('Folge eins');
    }

    public function test_neuigkeit_fuer_mehrere_kurse_mit_gelesen_und_monatsgruppen(): void
    {
        $club = $this->in(function () {
            $c = Program::create(['slug' => 'club', 'title' => 'Club', 'type' => 'club', 'pacing' => 'self_paced', 'is_published' => true]);
            ProgramMember::create(['program_id' => $c->id, 'user_id' => $this->bea->id]);
            Post::create(['title' => 'Info für Hybrid und Club', 'slug' => 'info', 'type' => 'neuigkeit', 'body' => 'Text', 'visibility' => 'program', 'program_id' => $this->hybrid->id, 'program_ids' => [$c->id], 'published_at' => now()->subDay()]);
            Post::create(['title' => 'Alte Info', 'slug' => 'alt', 'type' => 'neuigkeit', 'body' => 'Text', 'visibility' => 'members', 'published_at' => now()->subMonths(2)]);

            return $c;
        });
        // Bea (nur Club) sieht die Info ueber program_ids, mit "Neu" und Zaehler; nach dem Lesen nicht mehr
        $seite = $this->actingAs($this->bea)->get('http://a.test/impulse?f=neuigkeit')->assertOk();
        $seite->assertSee('Info für Hybrid und Club')->assertSee('Neu</span>', false)->assertSee('Neuigkeiten <span class="zahl">1</span>', false)
            ->assertSee(now()->subDay()->translatedFormat('F Y'))->assertSee(now()->subMonths(2)->translatedFormat('F Y'));
        $post = $this->in(fn () => Post::where('slug', 'info')->first());
        $this->actingAs($this->bea)->get("http://a.test/impulse/{$post->slug}")->assertOk();
        $this->actingAs($this->bea)->get('http://a.test/impulse?f=neuigkeit')->assertOk()->assertDontSee('Neuigkeiten <span class="zahl">', false);
        $this->actingAs($this->bea)->get('http://a.test/kurse/club')->assertOk()->assertSee('Info für Hybrid und Club');
        // Anna (Hybrid) sieht sie ueber program_id, eine Fremde nicht
        $this->actingAs($this->anna)->get('http://a.test/impulse?f=neuigkeit')->assertOk()->assertSee('Info für Hybrid und Club');
        $carla = User::factory()->create();
        $this->a->users()->attach($carla, ['role' => Role::Member->value, 'status' => 'active']);
        $this->actingAs($carla)->get('http://a.test/impulse?f=neuigkeit')->assertOk()->assertDontSee('Info für Hybrid und Club');
    }

    public function test_ressourcen_regal_mit_filtern_gehoert_zu_und_teilen(): void
    {
        [$pdf, $unit] = $this->in(function () {
            $u = $this->hybrid->units()->create(['title' => 'Atmen lernen', 'position' => 1]);
            $pdf = Resource::create(['title' => 'Arbeitsblatt Atem', 'type' => 'pdf', 'url' => 'https://example.ch/atem.pdf', 'file_name' => 'atem.pdf', 'description' => 'Zum Ausdrucken', 'summary' => '<p>Ein Blatt zum Atmen</p>']);
            Resourceable::create(['resource_id' => $pdf->id, 'resourceable_type' => 'program', 'resourceable_id' => $this->hybrid->id]);
            Resourceable::create(['resource_id' => $pdf->id, 'resourceable_type' => 'unit', 'resourceable_id' => $u->id]);
            $video = Resource::create(['title' => 'Impulsvideo', 'type' => 'video', 'url' => 'https://vimeo.com/555', 'image_url' => 'https://example.ch/bild.jpg', 'summary' => '<p>Worum es geht</p>']);
            Resourceable::create(['resource_id' => $video->id, 'resourceable_type' => 'user', 'resourceable_id' => $this->anna->id]);

            return [$pdf, $u];
        });
        $this->actingAs($this->anna)->get('http://a.test/material')->assertOk()->assertSee('Arbeitsblatt Atem')->assertSee('Impulsvideo')->assertSee('regal-bild')->assertSee('atem.pdf')->assertSee('in 1 Lektion');
        $this->actingAs($this->anna)->get('http://a.test/material?art=pdf')->assertOk()->assertSee('Arbeitsblatt Atem')->assertDontSee('Impulsvideo');
        $this->actingAs($this->anna)->get('http://a.test/material?h=geteilt')->assertOk()->assertDontSee('Arbeitsblatt Atem')->assertSee('Impulsvideo');
        $this->actingAs($this->anna)->get('http://a.test/material?sort=titel')->assertOk()->assertSeeInOrder(['Arbeitsblatt Atem', 'Impulsvideo']);
        // Einzelseite: Gehoert zu und Teilen, im Gespraech ist das Material schon angehaengt
        $this->actingAs($this->anna)->get("http://a.test/material/{$pdf->id}")->assertOk()->assertSee('Gehört zu:')->assertSee('Atmen lernen')->assertSee("/gespraech?ref=resource%3A{$pdf->id}");
        $this->actingAs($this->anna)->get("http://a.test/gespraech?ref=resource:{$pdf->id}")->assertRedirect();
        $conv = $this->in(fn () => app(Chat::class)->directFor($this->anna));
        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}?ref=resource:{$pdf->id}")->assertOk()->assertSee('resource:'.$pdf->id)->assertSee('<div data-chat-anhang >', false);
    }
}

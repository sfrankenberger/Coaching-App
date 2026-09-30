<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitVideo;
use App\Models\User;
use App\Recordings\LektionsVideo;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LektionsVideoTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected Program $kurs;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['vimeo' => ['token' => 'vt']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        [$this->kurs, $this->unit] = $this->in(function () {
            $k = Program::create(['slug' => 'kurs', 'title' => 'Kurs']);
            ProgramMember::create(['program_id' => $k->id, 'user_id' => $this->anna->id]);
            $u = $k->units()->create(['title' => 'Die Schritte', 'position' => 1, 'videos' => [['url' => 'https://vimeo.com/987654', 'title' => 'Einführung'], ['url' => 'https://www.youtube.com/watch?v=abc', 'title' => 'Bonus']]]);

            return [$k, $u];
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_lektionsvideo_bekommt_abschrift_kapitel_und_nachlesen(): void
    {
        Http::fake([
            'api.vimeo.com/videos/987654?*' => Http::response(['name' => 'Einführung', 'duration' => 754]),
            'api.vimeo.com/videos/987654/texttracks' => Http::response(['data' => [['language' => 'de', 'link' => 'https://captions.vimeo.test/1.vtt']]]),
            'captions.vimeo.test/*' => Http::response("WEBVTT\n\n00:00:02.000 --> 00:00:05.000\nHeute geht es um die Schritte.\n"),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '<p><strong>Worum es geht:</strong> Die Schritte.</p><h3>Einstieg (ab 00:02)</h3><p>Los.</p>']], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]),
        ]);

        // Nur das Vimeo-Video wird aufbereitet, YouTube nicht
        $this->assertSame(['fertig' => 1, 'wartet' => 0, 'offen' => 0], $this->in(fn () => app(LektionsVideo::class)->lauf()));
        $v = $this->in(fn () => UnitVideo::first());
        $this->assertSame('987654', $v->vimeo_id);
        $this->assertSame('13 Min.', $v->duration);
        $this->assertStringContainsString('Heute geht es um die Schritte.', $v->transcript);
        $this->assertStringStartsWith('<p><strong>Worum es geht:</strong>', $v->summary);
        $this->assertSame('bereit', $v->prepare_status);
        $this->assertSame(['fertig' => 0, 'wartet' => 0, 'offen' => 0], $this->in(fn () => app(LektionsVideo::class)->lauf()), 'nichts mehr offen');

        $this->actingAs($this->anna)->get("http://a.test/kurse/kurs/einheit/{$this->unit->id}")->assertOk()
            ->assertSee('Zum Nachlesen, worum es ging')->assertSee('data-sprung="2"', false)->assertSee('data-video-info="0"', false)
            ->assertSee('Abschrift')->assertSee('13 Min.')->assertSee('data-medien="unit-'.$this->unit->id.'-1"', false);
    }

    public function test_ohne_textspur_wartet_es(): void
    {
        Http::fake([
            'api.vimeo.com/videos/987654?*' => Http::response(['name' => 'Einführung', 'duration' => 60]),
            'api.vimeo.com/videos/987654/texttracks' => Http::response(['data' => []]),
        ]);
        $this->assertSame(['fertig' => 0, 'wartet' => 1, 'offen' => 0], $this->in(fn () => app(LektionsVideo::class)->lauf()));
        $this->assertSame('wartet', $this->in(fn () => UnitVideo::first()->prepare_status));
        $this->actingAs($this->anna)->get("http://a.test/kurse/kurs/einheit/{$this->unit->id}")->assertOk()->assertDontSee('Zum Nachlesen');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Recordings\MaterialVideo;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaterialVideoTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected Resource $video;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['vimeo' => ['token' => 'vt']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->video = $this->in(function () {
            $k = Program::create(['slug' => 'kurs', 'title' => 'Kurs']);
            ProgramMember::create(['program_id' => $k->id, 'user_id' => $this->anna->id]);
            $r = Resource::create(['title' => 'Die Prozessschritte', 'type' => 'link', 'url' => 'https://vimeo.com/987654/abc123']);
            $r->links()->create(['resourceable_type' => 'program', 'resourceable_id' => $k->id]);

            return $r;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_video_bekommt_dauer_bild_abschrift_und_zusammenfassung(): void
    {
        Http::fake([
            'api.vimeo.com/videos/987654?*' => Http::response(['name' => 'Prozess', 'duration' => 754, 'pictures' => ['sizes' => [['width' => 640, 'link' => 'bild.jpg']]]]),
            'api.vimeo.com/videos/987654/texttracks' => Http::response(['data' => [['language' => 'de', 'link' => 'https://captions.vimeo.test/1.vtt']]]),
            'captions.vimeo.test/*' => Http::response("WEBVTT\n\n00:00:02.000 --> 00:00:05.000\nHeute geht es um die Schritte.\n"),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => "```html\n<p><strong>Worum es geht:</strong> Die Schritte.</p><h3>Einstieg (ab 00:02)</h3><p>Los.</p>\n```"]], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]),
        ]);

        $b = $this->in(fn () => app(MaterialVideo::class)->lauf());
        $this->assertSame(['fertig' => 1, 'wartet' => 0, 'offen' => 0], $b);

        $r = $this->video->fresh();
        $this->assertSame('video', $r->type);
        $this->assertSame('987654', $r->vimeo_id);
        $this->assertSame('13 Min.', $r->duration);
        $this->assertSame('bild.jpg', $r->image_url);
        $this->assertStringContainsString('[00:02] Heute geht es um die Schritte.', $r->transcript);
        $this->assertStringStartsWith('<p><strong>Worum es geht:</strong>', $r->summary);
        $this->assertSame('bereit', $r->prepare_status);
        $this->assertSame(['fertig' => 0, 'wartet' => 0, 'offen' => 0], $this->in(fn () => app(MaterialVideo::class)->lauf()), 'nichts mehr offen');

        $this->actingAs($this->anna)->get("http://a.test/material/{$r->id}")->assertOk()
            ->assertSee('Die Prozessschritte')->assertSee('Worum es geht')->assertSee('data-sprung="2"', false)->assertSee('player.vimeo.com/video/987654');
        $this->actingAs($this->anna)->get('http://a.test/material')->assertOk()->assertSee("http://a.test/material/{$r->id}");
    }

    public function test_ohne_textspur_wartet_es_und_gibt_irgendwann_auf(): void
    {
        Http::fake([
            'api.vimeo.com/videos/987654?*' => Http::response(['name' => 'Prozess', 'duration' => 60]),
            'api.vimeo.com/videos/987654/texttracks' => Http::response(['data' => []]),
        ]);
        $this->assertSame('wartet', $this->in(fn () => app(MaterialVideo::class)->aufbereiten($this->video)));
        $this->video->forceFill(['prepare_tries' => MaterialVideo::VERSUCHE - 1])->save();
        $this->assertSame('ohne_abschrift', $this->in(fn () => app(MaterialVideo::class)->aufbereiten($this->video->fresh())));
        $this->assertCount(0, $this->in(fn () => app(MaterialVideo::class)->offen()), 'aufgegeben, nicht mehr in der Liste');

        // Fremde sehen die Seite nicht
        $bea = User::factory()->create();
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->actingAs($bea)->get("http://a.test/material/{$this->video->id}")->assertForbidden();
    }
}

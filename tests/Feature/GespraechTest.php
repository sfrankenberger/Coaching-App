<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Enums\Role;
use App\Jobs\ConvertAudio;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Reaction;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GespraechTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_1_zu_1_wird_angelegt_und_bleibt_privat(): void
    {
        $r = $this->actingAs($this->anna)->get('http://a.test/gespraech');
        $conv = $this->in(fn () => Conversation::where('user_id', $this->anna->id)->first());
        $r->assertRedirect("http://a.test/gespraech/{$conv->id}");
        $this->assertEqualsCanonicalizing([$this->lea->id, $this->anna->id], $this->in(fn () => $conv->participants()->pluck('user_id')->all()));

        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('Gespräch mit Lea');
        $this->actingAs($this->bea)->get("http://a.test/gespraech/{$conv->id}")->assertForbidden();
        $this->actingAs($this->lea)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('Gespräch mit Anna');
        $this->actingAs($this->lea)->get('http://a.test/gespraech')->assertOk()->assertSee('Anna Muster');
    }

    public function test_senden_nachfragen_gelesen_und_reaktion(): void
    {
        $conv = $this->in(fn () => app(Chat::class)->directFor($this->anna));

        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Hallo Lea, kurze Frage'])->assertOk()->assertJsonStructure(['id', 'html']);
        \Illuminate\Support\Facades\Notification::assertSentToTimes($this->lea, \App\Notifications\AppNotification::class, 1);
        $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", [])->assertStatus(422);
        $m = $this->in(fn () => Message::first());
        $this->assertSame('Hallo Lea, kurze Frage', $m->body);

        // Lea sieht 1 ungelesen, holt per Polling, danach gelesen
        $this->assertSame(1, $this->in(fn () => app(Chat::class)->unreadFor($this->lea)));
        $this->actingAs($this->lea)->getJson("http://a.test/gespraech/{$conv->id}/neu?seit=0")->assertOk()->assertJsonPath('letzte', $m->id);
        $this->assertSame(0, $this->in(fn () => app(Chat::class)->unreadFor($this->lea)));
        // Anna sieht jetzt den Doppelhaken-Zeitpunkt
        $this->actingAs($this->anna)->getJson("http://a.test/gespraech/{$conv->id}/neu?seit={$m->id}")->assertOk()->assertJsonPath('letzte', $m->id)->assertJsonMissing(['gelesen_bis' => null]);

        // Reaktion: nur auf fremde Nachrichten
        $this->actingAs($this->anna)->postJson("http://a.test/nachricht/{$m->id}/reaktion", ['emoji' => 'herz'])->assertStatus(422);
        $this->actingAs($this->lea)->postJson("http://a.test/nachricht/{$m->id}/reaktion", ['emoji' => 'herz'])->assertOk();
        $this->assertSame(1, $this->in(fn () => Reaction::count()));
        $this->actingAs($this->lea)->postJson("http://a.test/nachricht/{$m->id}/reaktion", ['emoji' => 'herz'])->assertOk();
        $this->assertSame(0, $this->in(fn () => Reaction::count()));
        $this->actingAs($this->bea)->postJson("http://a.test/nachricht/{$m->id}/reaktion", ['emoji' => 'herz'])->assertForbidden();
    }

    public function test_sprachnachricht_und_datei(): void
    {
        Queue::fake();
        $conv = $this->in(fn () => app(Chat::class)->directFor($this->anna));

        $this->actingAs($this->lea)->postJson("http://a.test/gespraech/{$conv->id}/senden", [
            'audio' => UploadedFile::fake()->create('sprachnachricht.webm', 120, 'audio/webm'), 'sek' => 42,
        ])->assertOk();
        $m = $this->in(fn () => Message::latest('id')->first());
        $this->assertTrue($m->hasAudio());
        $this->assertSame(42, $m->audio_seconds);
        Storage::disk('local')->assertExists($m->audio_path);
        $this->assertStringStartsWith("tenants/{$this->a->id}/chat/", $m->audio_path);
        Queue::assertPushed(ConvertAudio::class, fn ($j) => $j->messageId === $m->id && $j->tenantId === $this->a->id);

        $this->actingAs($this->anna)->get("http://a.test/nachricht/{$m->id}/audio")->assertOk();
        $this->actingAs($this->bea)->get("http://a.test/nachricht/{$m->id}/audio")->assertForbidden();

        $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", [
            'file' => UploadedFile::fake()->image('foto.jpg'), 'body' => 'Schau mal',
        ])->assertOk();
        $f = $this->in(fn () => Message::latest('id')->first());
        $this->assertTrue($f->attachmentIsImage());
        $this->assertSame('foto.jpg', $f->attachment_name);
    }

    public function test_gegenueber_ist_die_coachin_nicht_das_team(): void
    {
        $andrea = User::factory()->create(['name' => 'Andrea Team']);
        $this->a->users()->attach($andrea, ['role' => Role::Team->value, 'status' => 'active']);
        $conv = $this->in(fn () => app(Chat::class)->directFor($this->anna));

        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('Gespräch mit Lea')->assertDontSee('Gespräch mit Andrea');

        $this->a->forceFill(['settings' => ['coach_name' => 'Lea W.']])->save();
        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('Gespräch mit Lea W.');
    }

    public function test_gruppe_je_programm(): void
    {
        $program = $this->in(function () {
            $p = Program::create(['slug' => 'kurs', 'title' => 'Kurs A', 'type' => 'hybrid']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return $p;
        });

        $r = $this->actingAs($this->anna)->get('http://a.test/kurse/kurs/austausch');
        $conv = $this->in(fn () => Conversation::where('type', 'group')->where('program_id', $program->id)->first());
        $r->assertRedirect("http://a.test/gespraech/{$conv->id}");
        $this->actingAs($this->anna)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('Austausch in der Gruppe')->assertSee('Kurs A');
        $this->actingAs($this->bea)->get('http://a.test/kurse/kurs/austausch')->assertForbidden();
        $this->actingAs($this->bea)->get("http://a.test/gespraech/{$conv->id}")->assertForbidden();

        $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", ['body' => 'Hallo Gruppe'])->assertOk();
        $this->actingAs($this->lea)->get("http://a.test/gespraech/{$conv->id}")->assertOk()->assertSee('Hallo Gruppe')->assertSee('Anna');
    }
}

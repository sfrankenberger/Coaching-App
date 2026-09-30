<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Pages\Verbindungen;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

/** Sprachnachrichten abschreiben (AssemblyAI, OpenAI) und die Schluessel je Mandant pflegen. */
class TranskriptTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
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

    protected function sprachnachricht(): Message
    {
        $this->actingAs($this->anna)->get('http://a.test/gespraech');
        $conv = $this->in(fn () => Conversation::where('user_id', $this->anna->id)->first());
        $this->actingAs($this->anna)->postJson("http://a.test/gespraech/{$conv->id}/senden", [
            'audio' => UploadedFile::fake()->createWithContent('sprachnachricht.webm', str_repeat('audio', 100)), 'sek' => 7,
        ])->assertOk();

        return $this->in(fn () => Message::where('conversation_id', $conv->id)->orderByDesc('id')->first());
    }

    public function test_assemblyai_schreibt_die_sprachnachricht_ab(): void
    {
        config(['services.ffmpeg.enabled' => false]);
        Sleep::fake();
        $this->a->forceFill(['settings' => ['audio' => ['anbieter' => 'assemblyai', 'assemblyai_key' => 'aai-test']]])->save();
        Http::fake([
            'api.assemblyai.com/v2/upload' => Http::response(['upload_url' => 'https://cdn.assemblyai.com/upload/abc']),
            'api.assemblyai.com/v2/transcript' => Http::response(['id' => 'tr-1', 'status' => 'queued']),
            'api.assemblyai.com/v2/transcript/tr-1' => Http::sequence()
                ->push(['id' => 'tr-1', 'status' => 'processing'])
                ->push(['id' => 'tr-1', 'status' => 'completed', 'text' => 'Hallo Lea, das ist ein Test.']),
        ]);

        $m = $this->sprachnachricht();
        $this->assertSame('Hallo Lea, das ist ein Test.', $m->transcript);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/transcript') && ! str_contains($r->url(), 'tr-1') && $r['language_code'] === 'de' && $r->hasHeader('authorization', 'aai-test'));
        $this->actingAs($this->lea)->get("http://a.test/gespraech/{$m->conversation_id}")->assertOk()->assertSee('Transkript')->assertSee('Hallo Lea, das ist ein Test.');
    }

    public function test_openai_whisper_und_ohne_dienst_kein_transkript(): void
    {
        config(['services.ffmpeg.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['text' => 'Whisper hat mitgeschrieben'])]);

        $m = $this->sprachnachricht();
        $this->assertNull($m->transcript, 'ohne Dienst kein Transkript');
        Http::assertNothingSent();

        $this->a->forceFill(['settings' => ['audio' => ['anbieter' => 'openai', 'openai_key' => 'sk-test']]])->save();
        Cache::flush();
        $m = $this->sprachnachricht();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'openai.com'));
        $this->assertSame('Whisper hat mitgeschrieben', $m->transcript);
    }

    public function test_schluessel_je_mandant_pflegen_ohne_sie_zu_zeigen(): void
    {
        $this->a->forceFill(['settings' => ['ai' => ['anthropic_key' => 'sk-ant-alt1234']]])->save();
        $this->in(function () {
            Filament::setCurrentPanel(Filament::getPanel('coach'));
            $this->actingAs($this->lea);
            Livewire::test(Verbindungen::class)
                ->assertSee('hinterlegt, endet auf 1234')
                ->assertDontSee('sk-ant-alt1234')
                ->fillForm(['audio_anbieter' => 'assemblyai', 'assemblyai_key' => 'aai-neu', 'vimeo_token' => '-', 'zoom_account_id' => 'acc-1', 'stripe_public_key' => 'pk_test_1', 'stripe_secret_key' => 'sk_test_9999'])
                ->call('speichern')->assertHasNoFormErrors();
        });
        $t = $this->a->fresh();
        $this->assertSame('sk-ant-alt1234', $t->setting('ai.anthropic_key'), 'leer lassen behaelt den Schluessel');
        $this->assertSame('aai-neu', $t->setting('audio.assemblyai_key'));
        $this->assertSame('assemblyai', $t->setting('audio.anbieter'));
        $this->assertNull($t->setting('vimeo.token'));
        $this->assertSame('acc-1', $t->setting('zoom.account_id'));
        $this->assertSame('pk_test_1', $t->setting('stripe.public_key'));
        $this->assertSame('sk_test_9999', $t->setting('stripe.secret_key'));

        // Teilnehmerinnen kommen nicht an die Seite
        $this->in(function () {
            Filament::setCurrentPanel(Filament::getPanel('coach'));
            $this->actingAs($this->anna);
            $this->assertFalse(Verbindungen::canAccess());
        });
    }

    public function test_mailgun_je_mandant_und_testmail(): void
    {
        $this->a->forceFill(['settings' => ['mail' => ['mailgun_domain' => 'mg.a.test', 'mailgun_secret' => 'key-a', 'from_address' => 'hallo@a.test']]])->save();
        $vorher = config('services.mailgun.domain');
        $this->in(function () {
            $this->assertSame('mg.a.test', config('services.mailgun.domain'));
            $this->assertSame('key-a', config('services.mailgun.secret'));
            $this->assertSame('mailgun', config('mail.default'));
        });
        $this->assertSame($vorher, config('services.mailgun.domain'), 'nach dem Lauf wieder die Plattform');

        Notification::fake();
        $this->in(function () {
            Filament::setCurrentPanel(Filament::getPanel('coach'));
            $this->actingAs($this->lea);
            Livewire::test(Verbindungen::class)->call('testMail');
        });
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n, $channels) => str_starts_with($n->nachricht->titel, 'Test-Mail') && in_array('mail', $channels, true) && str_contains($n->nachricht->text, 'mg.a.test'));
    }
}

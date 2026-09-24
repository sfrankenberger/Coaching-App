<?php

namespace Tests\Feature;

use App\Ai\Summarizer;
use App\Enums\Role;
use App\Jobs\SummarizeEvent;
use App\Models\AiSummary;
use App\Models\Event;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        foreach ([[$this->lea, Role::Owner], [$this->anna, Role::Member], [$this->bea, Role::Member]] as [$u, $r]) {
            $this->a->users()->attach($u, ['role' => $r->value, 'status' => 'active']);
        }
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function antwort(array $json): array
    {
        return ['content' => [['type' => 'text', 'text' => "```json\n".json_encode($json, JSON_UNESCAPED_UNICODE)."\n```"]], 'model' => 'claude-test', 'usage' => ['input_tokens' => 120, 'output_tokens' => 80]];
    }

    public function test_zusammenfassung_mit_aufgaben_fuer_gruppencall(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->antwort([
            'zusammenfassung' => "Es ging um Ruhe.\n\nUnd um Vertrauen.",
            'kernsaetze' => ['Weniger ist mehr.'],
            'aufgaben' => [['titel' => 'Jeden Morgen 5 Minuten still sitzen', 'text' => 'Ohne Handy.', 'fuer' => 'alle'], ['titel' => 'Brief an dich schreiben', 'text' => '', 'fuer' => 'Bea']],
        ]))]);
        Notification::fake();

        [$event, $kurs] = $this->in(function () {
            $kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            ProgramMember::create(['program_id' => $kurs->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $kurs->id, 'user_id' => $this->bea->id]);
            $event = Event::create(['program_id' => $kurs->id, 'title' => 'Call 1', 'starts_at' => now()->subDay(), 'transcript' => 'Lea: Heute reden wir über Ruhe. Bea, schreib dir einen Brief.']);

            return [$event, $kurs];
        });

        (new SummarizeEvent($this->a->id, $event->id, $this->lea->id))->handle(app(CurrentTenant::class), app(Summarizer::class), app(Notifier::class));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->hasHeader('x-api-key', 'sk-test') && $body['model'] === 'claude-test' && str_contains($body['messages'][0]['content'], 'Bea, schreib dir') && str_contains($body['system'], 'Schweizer');
        });

        $summary = $this->in(fn () => AiSummary::first());
        $this->assertSame('done', $summary->status);
        $this->assertSame(120, $summary->tokens_in);
        $this->assertCount(2, $summary->tasks);
        $this->assertStringContainsString('Kernsätze:', $this->in(fn () => Event::first()->summary));
        Notification::assertSentTo($this->lea, AppNotification::class);

        $n = $this->in(fn () => app(Summarizer::class)->createTasks($summary, [0, 1], $this->lea));
        $this->assertSame(3, $n, 'eine fuer alle (2 Personen) + eine fuer Bea');
        $this->in(function () {
            $this->assertSame(2, Task::where('title', 'Jeden Morgen 5 Minuten still sitzen')->where('source', 'ai_summary')->count());
            $bea = Task::where('title', 'Brief an dich schreiben')->first();
            $this->assertSame($this->bea->id, $bea->user_id);
            $this->assertSame($this->lea->id, $bea->assigned_by);
            $this->assertSame('coach', $bea->visibility);
        });
        $this->assertSame(0, $this->in(fn () => app(Summarizer::class)->createTasks($summary, [0], $this->lea)), 'nicht doppelt');
    }

    public function test_person_uebernimmt_aufgabe_aus_ihrer_sitzung(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->antwort(['zusammenfassung' => 'Deine Sitzung.', 'aufgaben' => [['titel' => 'Spazieren gehen', 'text' => 'Täglich.', 'fuer' => 'Anna']]]))]);
        $event = $this->in(fn () => Event::create(['type' => 'one_on_one', 'user_id' => $this->anna->id, 'title' => 'Sitzung', 'starts_at' => now()->subDay(), 'transcript' => 'Text']));
        $this->in(fn () => app(Summarizer::class)->event($event));

        $this->actingAs($this->anna)->get('http://a.test/termine/'.$event->id)->assertOk()->assertSee('Deine Sitzung.')->assertSee('Spazieren gehen')->assertSee('Als Aufgabe');
        $this->actingAs($this->bea)->get('http://a.test/termine/'.$event->id)->assertForbidden();
        $this->actingAs($this->anna)->post('http://a.test/termine/'.$event->id.'/aufgabe', ['nr' => 0])->assertRedirect();
        $this->in(fn () => $this->assertSame(1, Task::where('user_id', $this->anna->id)->where('title', 'Spazieren gehen')->count()));
    }

    public function test_ohne_abschrift_oder_bei_fehler_bleibt_es_sauber(): void
    {
        $event = $this->in(fn () => Event::create(['title' => 'Leer', 'starts_at' => now()]));
        $s = $this->in(fn () => app(Summarizer::class)->event($event));
        $this->assertSame('failed', $s->status);
        $this->assertStringContainsString('Abschrift', $s->error);

        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'kaputt'], 500)]);
        $event2 = $this->in(fn () => Event::create(['title' => 'Fehler', 'starts_at' => now(), 'transcript' => 'Text']));
        $s2 = $this->in(fn () => app(Summarizer::class)->event($event2));
        $this->assertSame('failed', $s2->status);
        $this->assertStringContainsString('HTTP 500', $s2->error);
        $this->assertNull($event2->fresh()->summary);
    }

    public function test_podcastfolge_und_themenfinder(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push($this->antwort(['zusammenfassung' => 'Worum es geht.', 'kapitel' => [['start' => 0, 'titel' => 'Anfang'], ['start' => 200, 'titel' => 'Kern']], 'faq' => [['frage' => 'Wie?', 'antwort' => 'So.']], 'schlagworte' => ['Ruhe', 'Vertrauen']]))
            ->push($this->antwort(['themen' => ['Innere Ruhe', 'Neues Thema'], 'kurz' => 'Kurz gesagt.', 'hilft' => 'Hilft, wenn es laut wird.', 'stichworte' => ['Ruhe']])),
        ]);
        $e = $this->in(fn () => PodcastEpisode::create(['show' => 'S', 'guid' => 'g', 'title' => 'Folge', 'audio_url' => 'https://x.test/a.mp3', 'transcript' => 'Herzlich willkommen.']));
        $this->in(fn () => Topic::create(['name' => 'Innere Ruhe']));

        $s = $this->in(fn () => app(Summarizer::class)->episode($e));
        $this->assertSame('done', $s->status);
        $e = $e->fresh();
        $this->assertSame('Worum es geht.', $e->summary);
        $this->assertSame('Kern', $e->chapters[1]['titel']);
        $this->assertSame(['Ruhe', 'Vertrauen'], $e->keywords);

        $post = $this->in(fn () => Post::create(['title' => 'Impuls', 'body' => '<p>Text über Ruhe</p>']));
        $profile = $this->in(fn () => app(Summarizer::class)->finder($post));
        $this->assertSame('Kurz gesagt.', $profile->summary);
        $this->assertSame(['Innere Ruhe', 'Neues Thema'], $this->in(fn () => $post->topics()->pluck('name')->sort()->values()->all()));
        $this->assertSame(2, $this->in(fn () => Topic::count()));
    }
}

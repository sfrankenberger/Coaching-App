<?php

namespace Tests\Feature;

use App\Models\AiSummary;
use App\Models\Bookmark;
use App\Models\Event;
use App\Models\FinderProfile;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Taggable;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Models\WebhookLog;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regel 6: Mandant B sieht die Daten von A nicht (Tabellen aus Etappe 4). */
class InhalteIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mandant_b_sieht_nichts_von_a(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $user = User::factory()->create();
        $cur = app(CurrentTenant::class);

        $cur->run($a, function () use ($user) {
            $post = Post::create(['title' => 'Impuls A']);
            $episode = PodcastEpisode::create(['show' => 'S', 'guid' => 'g', 'title' => 'Folge A', 'audio_url' => 'https://x.test/a.mp3']);
            $topic = Topic::create(['name' => 'Thema A']);
            $post->topics()->attach($topic);
            $post->finder()->create(['summary' => 'Kurz']);
            $event = Event::create(['title' => 'Termin A', 'starts_at' => now()]);
            AiSummary::create(['summarizable_type' => 'event', 'summarizable_id' => $event->id, 'status' => 'done', 'body' => 'x']);
            WebhookLog::create(['source' => 'woocommerce', 'status' => 'ok']);
            Bookmark::create(['user_id' => $user->id, 'bookmarkable_type' => 'post', 'bookmarkable_id' => $post->id]);
        });

        $cur->run($b, function () {
            foreach ([Post::class, PodcastEpisode::class, Topic::class, Taggable::class, FinderProfile::class, AiSummary::class, WebhookLog::class, Bookmark::class] as $model) {
                $this->assertSame(0, $model::count(), $model);
            }
            // Gleicher Slug und gleiche GUID in B erlaubt (Eindeutigkeit mit tenant_id)
            Post::create(['title' => 'Impuls A']);
            PodcastEpisode::create(['show' => 'S', 'guid' => 'g', 'title' => 'Folge B', 'audio_url' => 'https://x.test/b.mp3']);
            Topic::create(['name' => 'Thema A']);
            $this->assertSame('impuls-a', Post::first()->slug);
        });

        $cur->run($a, function () {
            $this->assertSame(1, Post::count());
            $this->assertSame(1, Topic::count());
            $this->assertSame(1, Taggable::count());
        });
        $this->assertSame(2, Post::withoutGlobalScopes()->count());
    }
}

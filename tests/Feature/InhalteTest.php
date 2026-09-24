<?php

namespace Tests\Feature;

use App\Content\FeedImport;
use App\Enums\Role;
use App\Models\Bookmark;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Taggable;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InhalteTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['feeds' => [
            ['type' => 'post', 'url' => 'https://blog.test/feed'],
            ['type' => 'podcast', 'url' => 'https://pod.test/feed', 'show' => 'Abenteuer Leben'],
        ]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function rss(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/">
<channel><title>Blog</title>
<item><title>Der Moment auf der Treppe</title><link>https://blog.test/treppe</link><guid isPermaLink="false">blog-1</guid>
<pubDate>Fri, 18 Sep 2026 14:10:25 +0000</pubDate><category>ADHS im Alltag</category>
<description><![CDATA[Ich sass auf der Treppe.]]></description>
<content:encoded><![CDATA[<p>Ich sass auf der Treppe.</p><img src="https://blog.test/bild.jpg">]]></content:encoded></item>
<item><title>Zweiter Impuls</title><link>https://blog.test/zwei</link><guid>blog-2</guid><pubDate>Thu, 17 Sep 2026 08:00:00 +0000</pubDate>
<description>Kurz.</description><media:content url="https://blog.test/zwei.jpg" medium="image"/></item>
</channel></rss>
XML;
    }

    protected function podcastRss(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
<channel><title>Abenteuer Leben</title><itunes:image href="https://pod.test/cover.jpg"/>
<item><title>#12 Vertrauen</title><link>https://pod.test/12</link><guid>Kajabi-12</guid><pubDate>Mon, 14 Sep 2026 06:00:00 +0000</pubDate>
<enclosure url="https://pod.test/12.mp3" length="1000" type="audio/mpeg"/><itunes:duration>29:31</itunes:duration><itunes:episode>12</itunes:episode>
<itunes:summary>Worum es geht.</itunes:summary><description><![CDATA[<p>Shownotes</p>]]></description></item>
<item><title>Ohne Audio</title><guid>x</guid><description>kein enclosure</description></item>
</channel></rss>
XML;
    }

    public function test_feeds_werden_geholt_und_sind_wiederholbar(): void
    {
        Http::fake(['https://blog.test/feed' => Http::response($this->rss()), 'https://pod.test/feed' => Http::response($this->podcastRss())]);

        $stats = $this->in(fn () => (new FeedImport($this->a))->run());
        $this->assertSame(2, $stats['beitraege']);
        $this->assertSame(1, $stats['folgen']);

        $this->in(function () {
            $p = Post::where('legacy_id', 'feed:'.sha1('blog-1'))->first();
            $this->assertSame('der-moment-auf-der-treppe', $p->slug);
            $this->assertSame('https://blog.test/bild.jpg', $p->image_url, 'Bild aus dem Inhalt');
            $this->assertSame(['ADHS im Alltag'], $p->categories);
            $this->assertSame('feed', $p->source);
            $this->assertSame('2026-09-18 14:10:25', $p->published_at->toDateTimeString());
            $this->assertSame('https://blog.test/zwei.jpg', Post::where('title', 'Zweiter Impuls')->first()->image_url, 'media:content');

            $e = PodcastEpisode::where('guid', 'Kajabi-12')->first();
            $this->assertSame('Abenteuer Leben', $e->show);
            $this->assertSame(12, $e->episode_number);
            $this->assertSame(1771, $e->duration_seconds);
            $this->assertSame('https://pod.test/cover.jpg', $e->image_url, 'Bild vom Kanal');
            $this->assertSame('Worum es geht.', $e->excerpt);
            $this->assertSame('https://pod.test/12.mp3', $e->audio_url);
        });

        $this->in(fn () => Post::where('title', 'Zweiter Impuls')->first()->update(['is_published' => false, 'slug' => 'mein-slug']));
        $this->in(fn () => (new FeedImport($this->a))->run());
        $this->in(function () {
            $this->assertSame(2, Post::count());
            $this->assertSame(1, PodcastEpisode::count());
            $z = Post::where('title', 'Zweiter Impuls')->first();
            $this->assertFalse($z->is_published, 'Abschalten bleibt');
            $this->assertSame('mein-slug', $z->slug, 'Slug bleibt');
        });
        $this->assertSame(0, app(CurrentTenant::class)->run($this->b, fn () => Post::count()), 'Mandant B sieht nichts');
    }

    public function test_impulse_seiten_und_sichtbarkeit(): void
    {
        $this->in(function () {
            $kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            Post::create(['title' => 'Für alle', 'body' => '<p>Text für alle</p>', 'published_at' => now()->subDay()]);
            Post::create(['title' => 'Nur Kurs', 'visibility' => 'program', 'program_id' => $kurs->id, 'published_at' => now()->subDay()]);
            Post::create(['title' => 'Später', 'published_at' => now()->addDay()]);
            Post::create(['title' => 'Team', 'visibility' => 'team', 'published_at' => now()->subDay()]);
            PodcastEpisode::create(['show' => 'Abenteuer Leben', 'guid' => 'g1', 'title' => 'Folge eins', 'audio_url' => 'https://pod.test/1.mp3', 'published_at' => now()->subDays(2), 'chapters' => [['start' => 0, 'titel' => 'Anfang'], ['start' => 120, 'titel' => 'Mitte']]]);
        });

        $r = $this->actingAs($this->anna)->get('http://a.test/impulse');
        $r->assertOk()->assertSee('Für alle')->assertSee('Folge eins')->assertDontSee('Nur Kurs')->assertDontSee('Später')->assertDontSee('Team');

        $this->actingAs($this->anna)->get('http://a.test/impulse/fur-alle')->assertOk()->assertSee('Text für alle');
        $this->actingAs($this->anna)->get('http://a.test/impulse/nur-kurs')->assertNotFound();
        $this->in(fn () => ProgramMember::create(['program_id' => Program::first()->id, 'user_id' => $this->anna->id]));
        $this->actingAs($this->anna)->get('http://a.test/impulse/nur-kurs')->assertOk();
        $this->actingAs($this->lea)->get('http://a.test/impulse')->assertOk()->assertSee('Team')->assertSee('Später');

        $folge = $this->in(fn () => PodcastEpisode::first());
        $this->actingAs($this->anna)->get('http://a.test/impulse/folge/'.$folge->id)->assertOk()->assertSee('Anfang')->assertSee('02:00')->assertSee('https://pod.test/1.mp3');
        $this->actingAs($this->anna)->get('http://a.test/impulse?f=podcast')->assertOk()->assertSee('Folge eins')->assertDontSee('Für alle');
        $this->actingAs($this->anna)->get('http://a.test/impulse?q=eins')->assertOk()->assertSee('Folge eins')->assertDontSee('Für alle');
    }

    public function test_veroeffentlichen_meldet_einmal(): void
    {
        Notification::fake();
        $post = $this->in(fn () => Post::create(['title' => 'Neu hier', 'excerpt' => 'Kurz', 'author_id' => $this->lea->id, 'notify_channels' => ['push', 'mail'], 'published_at' => now()]));

        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->anlass === 'impuls' && str_contains($n->nachricht->titel, 'Neu hier'));
        Notification::assertNotSentTo($this->lea, AppNotification::class);
        $this->assertNotNull($post->fresh()->notified_at);

        $this->in(fn () => $post->update(['title' => 'Neu hier, angepasst']));
        Notification::assertSentToTimes($this->anna, AppNotification::class, 1);

        // Geplant: erst beim Lauf
        $spaeter = $this->in(fn () => Post::create(['title' => 'Morgen', 'notify_channels' => ['mail'], 'published_at' => now()->addHour()]));
        $this->assertNull($spaeter->fresh()->notified_at);
        $this->travel(2)->hours();
        $this->artisan('inhalte:veroeffentlichen')->assertSuccessful();
        $this->assertNotNull($spaeter->fresh()->notified_at);
        Notification::assertSentToTimes($this->anna, AppNotification::class, 2);
    }

    public function test_themenfinder_und_merkliste(): void
    {
        [$thema, $unit, $post] = $this->in(function () {
            $kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            $unit = Unit::create(['program_id' => $kurs->id, 'title' => 'Lektion Ruhe']);
            $post = Post::create(['title' => 'Impuls Ruhe', 'published_at' => now()->subDay()]);
            $geheim = Post::create(['title' => 'Team-Ruhe', 'visibility' => 'team', 'published_at' => now()->subDay()]);
            $thema = Topic::create(['name' => 'Innere Ruhe']);
            $leer = Topic::create(['name' => 'Leer']);
            $unit->topics()->attach($thema);
            $post->syncTopicsByName(['Innere Ruhe', 'Neues Thema']);
            $geheim->topics()->attach($thema);
            $post->finder()->create(['summary' => 'Worum es geht', 'helps' => 'Hilft bei Unruhe']);

            return [$thema, $unit, $post];
        });

        $this->assertSame($this->a->id, $this->in(fn () => Taggable::where('taggable_type', 'post')->where('taggable_id', $post->id)->first()->tenant_id), 'Pivot traegt tenant_id');
        $this->assertSame(3, $this->in(fn () => Topic::count()));

        $r = $this->actingAs($this->anna)->get('http://a.test/themen');
        $r->assertOk()->assertSee('Innere Ruhe')->assertSee('Neues Thema')->assertDontSee('Leer');

        // Anna ist nicht im Kurs: Lektion nicht, Impuls ja, Team-Impuls nicht
        $r = $this->actingAs($this->anna)->get('http://a.test/themen/innere-ruhe');
        $r->assertOk()->assertSee('Impuls Ruhe')->assertSee('Worum es geht')->assertDontSee('Lektion Ruhe')->assertDontSee('Team-Ruhe');
        $this->in(fn () => ProgramMember::create(['program_id' => Program::first()->id, 'user_id' => $this->anna->id]));
        $this->actingAs($this->anna)->get('http://a.test/themen/innere-ruhe')->assertOk()->assertSee('Lektion Ruhe');

        // Merken und Merkliste
        $this->actingAs($this->anna)->post('http://a.test/merken', ['type' => 'post', 'id' => $post->id])->assertRedirect();
        $this->actingAs($this->anna)->postJson('http://a.test/merken', ['type' => 'unit', 'id' => $unit->id])->assertOk()->assertJson(['an' => true, 'anzahl' => 2]);
        $this->actingAs($this->anna)->get('http://a.test/merkliste')->assertOk()->assertSee('Impuls Ruhe')->assertSee('Lektion Ruhe');
        $this->actingAs($this->anna)->postJson('http://a.test/merken', ['type' => 'post', 'id' => $post->id])->assertJson(['an' => false, 'anzahl' => 1]);
        $this->assertSame(1, $this->in(fn () => Bookmark::where('user_id', $this->anna->id)->count()));
        $this->actingAs($this->anna)->get('http://a.test/kurse/kurs/einheit/'.$unit->id)->assertOk()->assertSee('Gemerkt')->assertSee('Innere Ruhe');
    }
}

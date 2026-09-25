<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Import\WordPress\InhalteImport;
use App\Import\WordPress\WordPressSource;
use App\Models\Bookmark;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportInhalteTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_', 'foreign_key_constraints' => false]]);
        $wp = DB::connection('wordpress');
        $wp->getSchemaBuilder()->create('posts', function ($t) {
            $t->increments('ID');
            $t->string('post_title');
            $t->string('post_name');
            $t->text('post_content')->nullable();
            $t->text('post_excerpt')->nullable();
            $t->string('post_status');
            $t->string('post_type');
            $t->dateTime('post_date')->nullable();
            $t->dateTime('post_modified')->nullable();
            $t->integer('post_author')->default(0);
            $t->integer('menu_order')->default(0);
            $t->string('guid')->nullable();
        });
        $wp->getSchemaBuilder()->create('postmeta', function ($t) {
            $t->increments('meta_id');
            $t->unsignedInteger('post_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
        $wp->getSchemaBuilder()->create('usermeta', function ($t) {
            $t->increments('umeta_id');
            $t->unsignedInteger('user_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
        $wp->getSchemaBuilder()->create('terms', function ($t) {
            $t->increments('term_id');
            $t->string('name');
            $t->string('slug');
        });
        $wp->getSchemaBuilder()->create('term_taxonomy', function ($t) {
            $t->increments('term_taxonomy_id');
            $t->unsignedInteger('term_id');
            $t->string('taxonomy');
            $t->text('description')->nullable();
        });
        $wp->getSchemaBuilder()->create('term_relationships', function ($t) {
            $t->unsignedInteger('object_id');
            $t->unsignedInteger('term_taxonomy_id');
        });

        $this->lea = Tenant::create(['slug' => 'lea', 'name' => 'Lea', 'timezone' => 'Europe/Zurich', 'settings' => ['website' => 'https://example.ch', 'import' => ['wordpress' => [
            'news_categories' => [89], 'post_exclude_ids' => [999], 'club_visibility_slug' => 'club-intern', 'course_news_meta' => 'news_sichtbarkeit',
        ]]]]);
        $this->anna = User::factory()->create();
        $this->lea->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'legacy_id' => '21']);

        // Taxonomien: sichtbarkeit (24 free), category (5, 89), thema (164, 172), series (90), podcast_thema (112)
        foreach ([[30, 'Club intern', 'club-intern', 'sichtbarkeit'], [31, 'Kurs Hybrid', 'kurs-hybrid', 'sichtbarkeit'], [24, 'Free', 'free', 'sichtbarkeit'], [5, 'ADHS im Alltag', 'adhs-im-alltag', 'category'], [89, 'Neuigkeiten', 'neuigkeiten', 'category'], [1, 'Uncategorized', 'uncategorized', 'category'],
            [164, 'Innere Ruhe und Gelassenheit', 'innere-ruhe-und-gelassenheit', 'thema'], [172, 'Transformation', 'transformation', 'thema'], [90, 'Abenteuer Leben', 'abenteuer-leben', 'series'], [112, 'Innere Ruhe und Gelassenheit', 'innere-ruhe-und-gelassenheit', 'podcast_thema']] as [$id, $name, $slug, $tax]) {
            $wp->table('terms')->insert(['term_id' => $id, 'name' => $name, 'slug' => $slug]);
            $wp->table('term_taxonomy')->insert(['term_taxonomy_id' => $id, 'term_id' => $id, 'taxonomy' => $tax]);
        }
        $rel = fn (int $post, int ...$terms) => array_map(fn ($t) => $wp->table('term_relationships')->insert(['object_id' => $post, 'term_taxonomy_id' => $t]), $terms);

        // Beitraege
        $this->wpPost(2681, 'post', 'Der Moment auf der Treppe', 'der-moment-auf-der-treppe', "Ich sass auf der Treppe.\n\nDie Jungs waren weg.", ['_thumbnail_id' => '2680', 'lea_th_kurz' => 'Lea erzählt von einem Moment.', 'lea_th_hilft' => 'Hilft, wenn du im Chaos versinkst.', 'lea_th_stich' => serialize(['Treppe', 'Mutter']), 'lea_th_geprueft' => '1']);
        $rel(2681, 24, 5, 164);
        $this->wpPost(2680, 'attachment', 'treppe.jpg', 'treppe', '', ['_wp_attached_file' => '2026/09/treppe.jpg']);
        $this->wpPost(2565, 'post', 'Aufzeichnung ist da', 'aufzeichnung-ist-da', 'Text', []);
        $rel(2565, 24, 89);
        $this->wpPost(2479, 'post', 'Nur Newsletter', 'nur-newsletter', 'Text', []);
        $rel(2479, 5);
        $this->wpPost(999, 'post', 'Kampagne', 'kampagne', 'Text', []);
        $rel(999, 24);
        $this->wpPost(1000, 'post', 'Entwurf', 'entwurf', 'Text', [], 'draft');
        $rel(1000, 24);

        // Neuigkeiten nur fuer den Club und nur fuer einen Kurs (Sichtbarkeit am Kurs: news_sichtbarkeit)
        $this->wpPost(3000, 'post', 'Club-Call am Montag', 'club-call', 'Text', []);
        $rel(3000, 30);
        $this->wpPost(3001, 'post', 'Nur fuer Hybrid', 'nur-hybrid', 'Text', []);
        $rel(3001, 31);
        DB::connection('wordpress')->table('postmeta')->insert(['post_id' => 1849, 'meta_key' => 'news_sichtbarkeit', 'meta_value' => '31']);

        // Podcast
        $this->wpPost(2900, 'podcast', '#179 Das Leben ist ein Fluss', '179-das-leben', '<p>Shownotes</p>', [
            'audio_file' => 'https://app.kajabi.com/podcasts/medias/2148918467.mp3', 'duration' => '1771', 'date_recorded' => '2024-12-06 06:00:00', 'itunes_episode_number' => '179',
            'lea_feed_guid' => 'Kajabi-2148918467', 'lea_feed_link' => 'https://kajabi.test/179', 'lea_zusammenfassung' => 'Silvia und Lea verabschieden sich.',
            'lea_kapitel' => json_encode([['start' => 0, 'titel' => 'Ankündigung'], ['start' => 174, 'titel' => 'Der Start']]), 'lea_faq' => json_encode([['frage' => 'Wie?', 'antwort' => 'So.']]),
            'lea_schlagworte' => json_encode(['innerer Kompass', 'Abschied']), 'lea_transkript_html' => '<p class="lea-tr-abs" data-start="3">Herzlich willkommen</p>',
            'lea_th_kurz' => 'Abschied nach drei Jahren.', 'lea_th_hilft' => 'Hilft beim Loslassen.',
        ]);
        $rel(2900, 90, 112, 172);
        $this->wpPost(2901, 'podcast', 'Ohne Audio', 'ohne-audio', '', []);

        // Themen an Kurs und Lektion, Merkliste
        app(CurrentTenant::class)->run($this->lea, function () {
            $kurs = Program::create(['title' => 'Hybrid', 'slug' => 'hybrid', 'legacy_id' => '1849']);
            Program::create(['title' => 'Club', 'slug' => 'club', 'type' => 'club']);
            Unit::create(['program_id' => $kurs->id, 'title' => 'Willkommen', 'legacy_id' => '200']);
            Resource::create(['title' => 'Handout', 'legacy_id' => '400']);
        });
        $rel(1849, 172);
        $rel(200, 164);
        $this->wpPost(200, 'lektion', 'Willkommen', 'willkommen', '', ['lea_th_kurz' => 'Der Einstieg.']);
        $wp->table('usermeta')->insert(['user_id' => 21, 'meta_key' => 'lea_gemerkt', 'meta_value' => serialize(['ressource-400', 'lektion-200', 'post-2681', 'podcast-2900', 'termin-77', 'kaputt'])]);
    }

    protected function wpPost(int $id, string $type, string $title, string $name, string $content, array $meta, string $status = 'publish'): void
    {
        DB::connection('wordpress')->table('posts')->insert(['ID' => $id, 'post_type' => $type, 'post_title' => $title, 'post_name' => $name, 'post_content' => $content, 'post_status' => $status, 'post_date' => '2026-09-18 16:10:25', 'post_modified' => '2026-09-18 16:10:25', 'post_author' => 2]);
        foreach ($meta as $k => $v) {
            DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        }
    }

    protected function import(): array
    {
        return app(CurrentTenant::class)->run($this->lea, fn () => (new InhalteImport($this->lea, new WordPressSource))->run());
    }

    public function test_beitraege_podcast_themen_und_merkliste(): void
    {
        $stats = $this->import();

        app(CurrentTenant::class)->run($this->lea, function () use ($stats) {
            $this->assertSame(4, $stats['beitraege'], 'Free, Club und Kurs, ohne Ausschluss und Entwurf');
            $club = Post::where('legacy_id', '3000')->first();
            $this->assertSame(['neuigkeit', 'program', 'club'], [$club->type, $club->visibility, $club->program->slug]);
            $kursNews = Post::where('legacy_id', '3001')->first();
            $this->assertSame(['program', 'hybrid'], [$kursNews->visibility, $kursNews->program->slug]);
            $this->assertSame(1, $stats['folgen']);

            $p = Post::where('legacy_id', '2681')->first();
            $this->assertSame('impuls', $p->type);
            $this->assertSame('der-moment-auf-der-treppe', $p->slug);
            $this->assertSame('https://example.ch/wp-content/uploads/2026/09/treppe.jpg', $p->image_url);
            $this->assertSame(['ADHS im Alltag'], $p->categories);
            $this->assertSame('wordpress', $p->source);
            $this->assertSame('2026-09-18 14:10:25', $p->published_at->utc()->toDateTimeString(), 'Zuerich -> UTC');
            $this->assertNotNull($p->notified_at, 'alte Beitraege nicht nachmelden');
            $this->assertStringContainsString('<p>Ich sass auf der Treppe.</p>', $p->body);
            $this->assertSame(['Innere Ruhe und Gelassenheit'], $p->topics->pluck('name')->all());
            $this->assertSame('Lea erzählt von einem Moment.', $p->finder->summary);
            $this->assertSame(['Treppe', 'Mutter'], $p->finder->keywords);
            $this->assertTrue($p->finder->is_checked);
            $this->assertSame('neuigkeit', Post::where('legacy_id', '2565')->first()->type);
            $this->assertNull(Post::where('legacy_id', '2479')->first());
            $this->assertNull(Post::where('legacy_id', '999')->first());

            $e = PodcastEpisode::where('legacy_id', '2900')->first();
            $this->assertSame('Abenteuer Leben', $e->show);
            $this->assertSame('Kajabi-2148918467', $e->guid);
            $this->assertSame(179, $e->episode_number);
            $this->assertSame(1771, $e->duration_seconds);
            $this->assertSame('2024-12-06 05:00:00', $e->published_at->utc()->toDateTimeString());
            $this->assertSame('Ankündigung', $e->chapters[0]['titel']);
            $this->assertSame('So.', $e->faq[0]['antwort']);
            $this->assertSame(['innerer Kompass', 'Abschied'], $e->keywords);
            $this->assertStringContainsString('data-start="3"', $e->transcript);
            $this->assertSame('Silvia und Lea verabschieden sich.', $e->summary);
            $this->assertSame(['Innere Ruhe und Gelassenheit', 'Transformation'], $e->topics->pluck('name')->sort()->values()->all(), 'thema und podcast_thema, gleicher Name = ein Thema');
            $this->assertSame('Hilft beim Loslassen.', $e->finder->helps);
            $this->assertNull(PodcastEpisode::where('legacy_id', '2901')->first());

            $this->assertSame(2, Topic::count(), 'Doppelter Name aus zwei Taxonomien nur einmal');
            $this->assertSame(['Transformation'], Program::where('legacy_id', '1849')->first()->topics->pluck('name')->all());
            $unit = Unit::where('legacy_id', '200')->first();
            $this->assertSame(['Innere Ruhe und Gelassenheit'], $unit->topics->pluck('name')->all());
            $this->assertSame('Der Einstieg.', $unit->finder->summary);

            $marks = Bookmark::where('user_id', $this->anna->id)->get()->map(fn ($b) => $b->bookmarkable_type.'-'.$b->bookmarkable_id)->sort()->values()->all();
            $this->assertSame(collect(['resource-'.Resource::first()->id, 'unit-'.$unit->id, 'post-'.$p->id, 'episode-'.$e->id])->sort()->values()->all(), $marks);
            $this->assertSame(4, $stats['merker']);
        });

        // Wiederholbar
        $this->import();
        app(CurrentTenant::class)->run($this->lea, function () {
            $this->assertSame(4, Post::count());
            $this->assertSame(1, PodcastEpisode::count());
            $this->assertSame(2, Topic::count());
            $this->assertSame(4, Bookmark::count());
        });
    }
}

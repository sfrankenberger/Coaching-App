<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Import\WordPress\BegleitungImport;
use App\Import\WordPress\WordPressSource;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Reaction;
use App\Models\Reflection;
use App\Models\Resource;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportBegleitungTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $lea;

    protected User $coach;

    protected User $anna;

    protected User $bea;

    protected Program $hybrid;

    protected ProgramStep $boden;

    protected ProgramStep $rad;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_', 'foreign_key_constraints' => false]]);
        $wp = DB::connection('wordpress');
        $wp->getSchemaBuilder()->create('users', function ($t) {
            $t->increments('ID');
            $t->string('user_login');
            $t->string('user_email');
            $t->string('display_name');
            $t->dateTime('user_registered');
        });
        $wp->getSchemaBuilder()->create('usermeta', function ($t) {
            $t->increments('umeta_id');
            $t->unsignedInteger('user_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
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
        });
        $wp->getSchemaBuilder()->create('postmeta', function ($t) {
            $t->increments('meta_id');
            $t->unsignedInteger('post_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
        $wp->getSchemaBuilder()->create('jet_rel_default', function ($t) {
            $t->increments('_ID');
            $t->string('rel_id');
            $t->unsignedInteger('parent_object_id');
            $t->unsignedInteger('child_object_id');
        });
        $wp->getSchemaBuilder()->create('comments', function ($t) {
            $t->increments('comment_ID');
            $t->unsignedInteger('comment_post_ID');
            $t->unsignedInteger('user_id')->default(0);
            $t->text('comment_content')->nullable();
            $t->string('comment_approved')->default('1');
            $t->dateTime('comment_date')->nullable();
        });
        $wp->getSchemaBuilder()->create('commentmeta', function ($t) {
            $t->increments('meta_id');
            $t->unsignedInteger('comment_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });

        $this->lea = Tenant::create(['slug' => 'lea', 'name' => 'Lea', 'timezone' => 'Europe/Zurich', 'settings' => ['import' => ['wordpress' => [
            'owner_ids' => [2], 'team_roles' => ['administrator'],
        ]]]]);
        $this->coach = User::factory()->create(['name' => 'Lea Wernli']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->lea->users()->attach($this->coach, ['role' => Role::Owner->value, 'status' => 'active', 'legacy_id' => '2']);
        $this->lea->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'legacy_id' => '21']);
        $this->lea->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active', 'legacy_id' => '22']);
        $this->wpUser(2, 'lea@example.com', 'Lea Wernli', ['administrator']);
        $this->wpUser(21, 'anna@example.com', 'Anna', ['customer']);
        $this->wpUser(22, 'bea@example.com', 'Bea', ['customer']);

        app(CurrentTenant::class)->run($this->lea, function () {
            $this->hybrid = Program::create(['title' => 'Hybrid-Coaching', 'slug' => 'hybrid', 'type' => 'hybrid', 'pacing' => 'weekly', 'legacy_id' => '1849']);
            $this->boden = ProgramStep::create(['program_id' => $this->hybrid->id, 'title' => 'Schritt 1: Boden', 'position' => 1, 'legacy_id' => '100']);
            $this->rad = ProgramStep::create(['program_id' => $this->hybrid->id, 'title' => 'Schritt 2: Rad', 'position' => 2, 'legacy_id' => '101']);
            $this->unit = Unit::create(['program_id' => $this->hybrid->id, 'step_id' => $this->boden->id, 'title' => 'Willkommen', 'legacy_id' => '200']);
            ProgramMember::create(['program_id' => $this->hybrid->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $this->hybrid->id, 'user_id' => $this->bea->id]);
        });

        // Termine (termin_start = lokale Wanduhr als Unix-Zeit, wie in WordPress gespeichert)
        $this->wpPost(300, 'termin', 'Gruppencall Schritt 1: Boden', 'call-1', "Wir starten.\n\nBring dein Buch mit.", ['termin_start' => (string) gmmktime(19, 0, 0, 9, 7, 2026), 'termin_art' => 'call', 'zoom_link' => 'https://zoom.us/j/1', 'lea_nicht_dabei' => serialize([22, 77])]);
        $this->wpPost(301, 'termin', 'Gruppencall: Rad', 'call-2', '', ['termin_start' => (string) gmmktime(19, 0, 0, 9, 16, 2026), 'termin_art' => 'call', 'recording_url' => 'https://vimeo.com/2/xyz']);
        $this->wpPost(302, 'termin', 'Call mit Anna', 'call-anna', '', ['termin_start' => (string) gmmktime(10, 0, 0, 9, 10, 2026), 'termin_art' => 'call', 'nvc_person' => '21', 'zoom_link' => 'https://zoom.us/j/2'], 'private');
        $this->wpPost(303, 'termin', 'Reflexionstag', 'reflexionstag', '', ['termin_start' => (string) gmmktime(0, 0, 0, 9, 12, 2026), 'termin_art' => 'reflexion']);
        $this->wpPost(304, 'termin', 'Call mit Unbekannt', 'call-x', '', ['termin_start' => (string) gmmktime(10, 0, 0, 9, 11, 2026), 'termin_art' => 'call', 'nvc_person' => '99']);
        $this->umeta(21, 'lea_live_dabei', serialize([300]));
        $this->umeta(22, 'lea_angeschaut', serialize([301]));

        // Material
        $this->wpPost(400, 'ressource', 'Handout Boden', 'handout', '', ['ressource_typ' => 'pdf', 'ressource_url' => 'https://leawernli.ch/wp-content/uploads/2025/handout.pdf', 'ressource_beschreibung' => 'Zum Ausdrucken']);
        $this->wpPost(401, 'ressource', 'Artikel', 'artikel', '', ['ressource_typ' => 'link', 'ressource_url' => 'https://example.com/text.pdf']);
        $this->wpPost(402, 'ressource', 'Altes Audio', 'audio', '', ['ressource_typ' => 'audio', 'ressource_url' => 'https://example.com/a.mp3', 'nur_archiv' => '1']);

        // Aufgaben
        $this->wpPost(500, 'aufgabe', 'Buch lesen', 'buch-lesen', 'Kapitel 1', ['el_sicht' => 'privat', 'af_faellig' => '2026-09-20', 'af_zeit' => '08:30', 'af_taeglich' => '1', 'af_erledigt' => (string) gmmktime(12, 0, 0, 9, 18, 2026)], 'publish', 21);
        $this->wpPost(501, 'aufgabe', 'Werte notieren', 'werte', '', ['el_sicht' => 'kurs', 'af_kurs' => '1849', 'af_faellig' => '2026-09-25'], 'publish', 2);
        $this->wpPost(502, 'aufgabe', 'Video schauen', 'video', '', ['el_sicht' => 'privat'], 'publish', 21);
        $this->umeta(22, 'lea_af_fremd_fertig', serialize([501]));

        // Notizen, Reflexionen, Journal
        $this->wpPost(600, 'notiz', 'Gedanke', 'gedanke', '<p>Mehr Ruhe am Morgen.</p>', ['el_sicht' => 'lea', 'notiz_url' => 'https://example.com/ruhe', 'notiz_kurs' => '1849', 'el_reaktionen' => serialize(['herz' => [2], 'unbekannt' => [2]])], 'publish', 21);
        $this->wpPost(700, 'reflexion', 'Woche 1', 'woche-1', '', ['refl_an_lea' => '1', 'refl_gut' => 'Viel geschafft', 'refl_schwer' => 'Wenig Schlaf', 'refl_fokus' => 'Frueher ins Bett', 'el_kurs' => '1849'], 'publish', 21);
        $this->wpPost(701, 'reflexion', 'Woche 1', 'woche-1-bea', '', ['refl_gut' => 'Ruhe'], 'private', 22);
        $this->wpPost(800, 'journal', 'Buchprojekt', 'buchprojekt', 'Exposé schreiben', ['journal_typ' => 'projekt', 'journal_faellig' => '2026-10-01', 'journal_projekt' => 'Buch', 'el_sicht' => 'lea'], 'publish', 21);

        // Kommentar der Coachin an der Notiz
        $this->wpComment(1, 600, 2, 'Schön, bleib dran.');

        // Chat von Anna: Kommentare am chat-Post
        $this->wpPost(900, 'chat', 'Chat Anna', 'chat-anna', '', [], 'private', 21);
        $this->wpPost(950, 'attachment', 'sprache.webm', 'sprache', '', ['_wp_attached_file' => '2026/09/sprache.webm']);
        $this->wpComment(10, 900, 21, 'Hallo Lea', '2026-09-08 09:00:00');
        $this->wpComment(11, 900, 2, 'Hallo Anna, schau mal deine Notiz an.', '2026-09-08 09:30:00', ['chat_ref' => '600', 'lea_ch_reaktionen' => serialize(['herz' => [21]])]);
        $this->wpComment(12, 900, 21, '', '2026-09-08 10:00:00', ['chat_audio' => '950', 'chat_audio_sek' => '12', 'chat_transkript' => 'Danke dir']);
        $this->wpComment(13, 900, 88, 'Fremd', '2026-09-08 11:00:00');
        $this->umeta(2, 'lea_ch_gesehen_900', (string) gmmktime(9, 45, 0, 9, 8, 2026));

        DB::connection('wordpress')->table('jet_rel_default')->insert([
            ['rel_id' => '19', 'parent_object_id' => 1849, 'child_object_id' => 300],
            ['rel_id' => '20', 'parent_object_id' => 101, 'child_object_id' => 301],
            ['rel_id' => '16', 'parent_object_id' => 1849, 'child_object_id' => 400],
            ['rel_id' => '12', 'parent_object_id' => 200, 'child_object_id' => 400],
            ['rel_id' => '18', 'parent_object_id' => 300, 'child_object_id' => 400],
            ['rel_id' => '17', 'parent_object_id' => 100, 'child_object_id' => 401],
            ['rel_id' => '38', 'parent_object_id' => 200, 'child_object_id' => 502],
        ]);
    }

    protected function wpUser(int $id, string $email, string $name, array $roles): void
    {
        DB::connection('wordpress')->table('users')->insert(['ID' => $id, 'user_login' => $email, 'user_email' => $email, 'display_name' => $name, 'user_registered' => '2025-01-01 10:00:00']);
        $this->umeta($id, 'wp_capabilities', serialize(array_fill_keys($roles, true)));
    }

    protected function wpPost(int $id, string $type, string $title, string $name, string $content, array $meta, string $status = 'publish', int $author = 2): void
    {
        DB::connection('wordpress')->table('posts')->insert(['ID' => $id, 'post_type' => $type, 'post_title' => $title, 'post_name' => $name, 'post_content' => $content, 'post_status' => $status, 'post_author' => $author, 'post_date' => '2026-09-01 08:00:00', 'post_modified' => '2026-09-02 08:00:00']);
        foreach ($meta as $k => $v) {
            DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        }
    }

    protected function wpComment(int $id, int $postId, int $userId, string $content, string $date = '2026-09-03 12:00:00', array $meta = []): void
    {
        DB::connection('wordpress')->table('comments')->insert(['comment_ID' => $id, 'comment_post_ID' => $postId, 'user_id' => $userId, 'comment_content' => $content, 'comment_approved' => '1', 'comment_date' => $date]);
        foreach ($meta as $k => $v) {
            DB::connection('wordpress')->table('commentmeta')->insert(['comment_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        }
    }

    protected function umeta(int $uid, string $k, string $v): void
    {
        DB::connection('wordpress')->table('usermeta')->insert(['user_id' => $uid, 'meta_key' => $k, 'meta_value' => $v]);
    }

    protected function import(bool $dryRun = false): array
    {
        return app(CurrentTenant::class)->run($this->lea, fn () => (new BegleitungImport($this->lea, new WordPressSource, $dryRun))->run());
    }

    public function test_termine_und_teilnahmen(): void
    {
        $stats = $this->import();

        app(CurrentTenant::class)->run($this->lea, function () use ($stats) {
            $this->assertSame(4, $stats['termine'], 'Termin mit unbekannter Person uebersprungen');
            $this->assertNotEmpty($stats['hinweise']);

            $call = Event::where('legacy_id', '300')->first();
            $this->assertSame('group_call', $call->type);
            $this->assertSame($this->hybrid->id, $call->program_id);
            $this->assertSame('2026-09-07 17:00:00', $call->starts_at->utc()->toDateTimeString(), 'Wanduhr Zuerich 19:00 = 17:00 UTC');
            $this->assertSame('https://zoom.us/j/1', $call->zoom_url);
            $this->assertStringContainsString('<p>Wir starten.</p>', $call->description);
            $this->assertNotNull($call->reminded_day_at, 'keine nachtraeglichen Erinnerungen');
            $this->assertSame('declined', EventAttendee::where('event_id', $call->id)->where('user_id', $this->bea->id)->value('status'));
            $this->assertSame('attended', EventAttendee::where('event_id', $call->id)->where('user_id', $this->anna->id)->value('status'));

            $rad = Event::where('legacy_id', '301')->first();
            $this->assertSame($this->rad->id, $rad->step_id);
            $this->assertSame($this->hybrid->id, $rad->program_id, 'Programm ueber den Schritt');
            $this->assertSame('https://vimeo.com/2/xyz', $rad->recording_url);
            $this->assertNotNull($rad->recording_notified_at);
            $this->assertSame('watched', EventAttendee::where('event_id', $rad->id)->where('user_id', $this->bea->id)->value('status'));

            $einzel = Event::where('legacy_id', '302')->first();
            $this->assertSame('one_on_one', $einzel->type);
            $this->assertSame($this->anna->id, $einzel->user_id);
            $this->assertFalse($einzel->is_published, 'privater Beitrag');

            $refl = Event::where('legacy_id', '303')->first();
            $this->assertSame('reflection_day', $refl->type);
            $this->assertTrue($refl->all_day);
            $this->assertNull(Event::where('legacy_id', '304')->first());
        });
    }

    public function test_material_mit_zuordnungen(): void
    {
        $stats = $this->import();

        app(CurrentTenant::class)->run($this->lea, function () use ($stats) {
            $this->assertSame(3, $stats['material']);
            $handout = Resource::where('legacy_id', '400')->first();
            $this->assertSame('pdf', $handout->type);
            $this->assertSame('Zum Ausdrucken', $handout->description);
            $this->assertNull($handout->file_path, 'ohne uploads_dir keine Kopie');
            $call = Event::where('legacy_id', '300')->first();
            $ziele = $handout->links->map(fn ($r) => $r->resourceable_type.':'.$r->resourceable_id)->sort()->values()->all();
            $this->assertSame(collect(['program:'.$this->hybrid->id, 'unit:'.$this->unit->id, 'event:'.$call->id])->sort()->values()->all(), $ziele);

            $artikel = Resource::where('legacy_id', '401')->first();
            $this->assertSame('pdf', $artikel->type, 'Link auf .pdf wird PDF');
            $this->assertSame('step:'.$this->boden->id, $artikel->links->map(fn ($r) => $r->resourceable_type.':'.$r->resourceable_id)->first());
            $this->assertTrue(Resource::where('legacy_id', '402')->first()->is_archived);
        });
    }

    public function test_aufgaben_notizen_reflexionen_journal(): void
    {
        $this->import();

        app(CurrentTenant::class)->run($this->lea, function () {
            $buch = Task::where('legacy_id', '500')->first();
            $this->assertSame($this->anna->id, $buch->user_id);
            $this->assertSame('2026-09-20', $buch->due_at->toDateString());
            $this->assertSame('08:30', $buch->due_time);
            $this->assertTrue($buch->is_daily);
            $this->assertNotNull($buch->done_at);
            $this->assertSame('Kapitel 1', $buch->body);
            $this->assertSame('manual', $buch->source);

            // Aufgabe der Coachin an den Kurs: je Teilnehmerin eine Kopie
            $this->assertNull(Task::where('legacy_id', '501')->first());
            $annaKopie = Task::where('legacy_id', '501-'.$this->anna->id)->first();
            $beaKopie = Task::where('legacy_id', '501-'.$this->bea->id)->first();
            $this->assertSame('coach', $annaKopie->source);
            $this->assertSame($this->coach->id, $annaKopie->assigned_by);
            $this->assertSame($this->hybrid->id, $annaKopie->program_id);
            $this->assertNull($annaKopie->done_at);
            $this->assertNotNull($beaKopie->done_at, 'lea_af_fremd_fertig');

            $video = Task::where('legacy_id', '502')->first();
            $this->assertSame($this->unit->id, $video->unit_id);
            $this->assertSame($this->hybrid->id, $video->program_id);
            $this->assertSame('program', $video->source);

            $note = Note::where('legacy_id', '600')->first();
            $this->assertSame('coach', $note->visibility);
            $this->assertSame("Mehr Ruhe am Morgen.\n\nhttps://example.com/ruhe", $note->body);
            $this->assertSame($this->hybrid->id, $note->program_id);
            $this->assertSame('2026-09-01 08:00:00', $note->created_at->toDateTimeString());
            $kommentar = Comment::where('legacy_id', '1')->first();
            $this->assertSame('note', $kommentar->commentable_type);
            $this->assertSame($note->id, $kommentar->commentable_id);
            $this->assertSame($this->coach->id, $kommentar->user_id);
            $this->assertSame(['herz'], Reaction::where('reactable_type', 'note')->where('reactable_id', $note->id)->pluck('emoji')->all(), 'unbekannte Emojis fallen weg');

            $r = Reflection::where('legacy_id', '700')->first();
            $this->assertSame('coach', $r->visibility);
            $this->assertNotNull($r->shared_at);
            $this->assertSame('Viel geschafft', $r->went_well);
            $this->assertSame('Frueher ins Bett', $r->focus);
            $this->assertSame($this->hybrid->id, $r->program_id);
            $bea = Reflection::where('legacy_id', '701')->first();
            $this->assertSame('private', $bea->visibility);
            $this->assertNull($bea->shared_at);

            $j = JournalEntry::where('legacy_id', '800')->first();
            $this->assertSame('projekt', $j->type);
            $this->assertSame('2026-10-01', $j->due_at->toDateString());
            $this->assertSame('Buch', $j->settings['projekt']);
            $this->assertSame('coach', $j->visibility);
        });
    }

    public function test_chats_und_lesestand(): void
    {
        $stats = $this->import();

        app(CurrentTenant::class)->run($this->lea, function () use ($stats) {
            $this->assertSame(1, $stats['gespraeche']);
            $this->assertSame(3, $stats['nachrichten'], 'Fremde Person uebersprungen');
            $conv = Conversation::where('legacy_id', '900')->first();
            $this->assertSame('direct', $conv->type);
            $this->assertSame($this->anna->id, $conv->user_id);
            $this->assertTrue(ConversationParticipant::where('conversation_id', $conv->id)->where('user_id', $this->coach->id)->exists(), 'Coachin ist dabei');

            $hallo = Message::where('legacy_id', '10')->first();
            $this->assertSame('Hallo Lea', $hallo->body);
            $this->assertSame($this->anna->id, $hallo->user_id);
            $this->assertSame('2026-09-08 09:00:00', $hallo->created_at->toDateTimeString());

            $antwort = Message::where('legacy_id', '11')->first();
            $this->assertSame($this->coach->id, $antwort->user_id);
            $this->assertSame('note', $antwort->ref_type);
            $this->assertSame(Note::where('legacy_id', '600')->value('id'), $antwort->ref_id);
            $this->assertSame(1, Reaction::where('reactable_type', 'message')->where('reactable_id', $antwort->id)->where('user_id', $this->anna->id)->where('emoji', 'herz')->count());

            $sprache = Message::where('legacy_id', '12')->first();
            $this->assertNull($sprache->body);
            $this->assertSame(12, $sprache->audio_seconds);
            $this->assertSame('Danke dir', $sprache->transcript);
            $this->assertNull($sprache->audio_path, 'ohne uploads_dir keine Datei');

            $this->assertSame('2026-09-08 10:00:00', $conv->last_message_at->toDateTimeString());
            $gelesen = ConversationParticipant::where('conversation_id', $conv->id)->where('user_id', $this->coach->id)->value('last_read_at');
            $this->assertSame('2026-09-08 09:45:00', (string) $gelesen);
            $this->assertSame(1, $conv->unreadCountFor($this->coach), 'Sprachnachricht von 10:00 noch ungelesen');
        });
    }

    public function test_wochen_aus_gruppencalls(): void
    {
        $this->import();

        app(CurrentTenant::class)->run($this->lea, function () {
            $boden = ProgramStep::find($this->boden->id);
            $rad = ProgramStep::find($this->rad->id);
            $this->assertSame(1, $boden->week_number, 'ueber den Titel gefunden');
            $this->assertSame('2026-09-06 22:00:00', $boden->unlocks_at->utc()->toDateTimeString(), 'Montag 00:00 Zuerich');
            $this->assertSame(2, $rad->week_number, 'ueber die Modul-Relation');
            $this->assertSame('2026-09-13 22:00:00', $rad->unlocks_at->utc()->toDateTimeString());
            $this->assertSame($boden->id, Event::where('legacy_id', '300')->value('step_id'), 'Call bekommt den Schritt');
        });
    }

    public function test_dry_run_schreibt_nichts_und_import_ist_wiederholbar(): void
    {
        $stats = $this->import(dryRun: true);
        app(CurrentTenant::class)->run($this->lea, function () use ($stats) {
            $this->assertSame(4, $stats['termine']);
            $this->assertSame(0, Event::count() + Resource::count() + Task::count() + Note::count() + Message::count());
        });

        $this->import();
        $this->import();
        app(CurrentTenant::class)->run($this->lea, function () {
            $this->assertSame(4, Event::count());
            $this->assertSame(3, Resource::count());
            $this->assertSame(4, Task::count(), '2 eigene + 2 Kopien');
            $this->assertSame(1, Conversation::count());
            $this->assertSame(3, Message::count());
            $this->assertSame(1, Comment::count());
            $this->assertSame(3, Resource::where('legacy_id', '400')->first()->links()->count());
        });
    }
}

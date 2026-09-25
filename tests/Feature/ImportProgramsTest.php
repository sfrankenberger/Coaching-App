<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Import\WordPress\ProgramsImport;
use App\Import\WordPress\WordPressSource;
use App\Models\Answer;
use App\Models\Entitlement;
use App\Models\Exercise;
use App\Models\Offer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Progress;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportProgramsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $lea;

    protected User $anna;

    protected User $bea;

    protected string $dir;

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
        $wp->getSchemaBuilder()->create('jet_rel_default', function ($t) {
            $t->increments('_ID');
            $t->string('rel_id');
            $t->unsignedInteger('parent_object_id');
            $t->unsignedInteger('child_object_id');
        });

        $this->dir = sys_get_temp_dir().'/wb-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/workbook-struktur.json', json_encode([[
            'nr' => 1, 'key' => 's01', 'titel' => 'BODEN', 'lead' => 'Warum machst du das?', 'art' => 'arbeit',
            'uebungen' => [[
                'nr' => 1, 'key' => 's01-u01', 'titel' => 'Deine Intention', 'badge' => 'KERN',
                'teile' => [['t' => 'lead', 'text' => 'Ehrlich bleiben.'], ['t' => 'frage', 'text' => 'Was soll anders sein?'], ['t' => 'sub', 'text' => 'Energie'], ['t' => 'skala', 'text' => 'Zufriedenheit:'], ['t' => 'werte', 'zeilen' => [['Mut', 'Ruhe'], ['Klarheit']]], ['t' => 'leer']],
            ]],
        ]]));
        file_put_contents($this->dir.'/arbeitsbuch-geld.json', json_encode(['buch' => ['titel' => 'Geldbuch'], 'schritte' => [['nr' => 1, 'key' => 'g01', 'titel' => 'Start', 'uebungen' => [['nr' => 1, 'key' => 'g01-u01', 'titel' => 'Kontostand', 'teile' => [['t' => 'frage', 'text' => 'Wie viel?']]]]]]]));

        // Arbeitsbuch mit den neuen Bausteinen (Liste, zwei Spalten, Brief, Spiegel, Aufnahme, Mitnehmen, Praxis)
        file_put_contents($this->dir.'/arbeitsbuch-liebesbrief.json', json_encode(['buch' => ['titel' => 'Liebesbrief'], 'schritte' => [
            ['nr' => 1, 'key' => 'lb01', 'titel' => 'Sammeln', 'uebungen' => [['nr' => 1, 'key' => 'lb01-u01', 'titel' => 'Wuensche', 'teile' => [
                ['t' => 'liste', 'text' => 'Was du dir wuenschst', 'platzhalter' => 'Ich wuensche mir ...', 'mehr' => 'Noch einer'],
                ['t' => 'liste2', 'links' => 'Der Gedanke', 'rechts' => 'Umgedreht'],
                ['t' => 'brieffeld', 'text' => 'Dein Brief', 'zeilen' => 18],
            ]]]],
            ['nr' => 2, 'key' => 'lb02', 'titel' => 'Lesen', 'uebungen' => [['nr' => 1, 'key' => 'lb02-u01', 'titel' => 'Laut lesen', 'teile' => [
                ['t' => 'spiegel', 'feld' => 'lb01-u01-f1', 'text' => 'Das hast du dir gewuenscht'],
                ['t' => 'aufnahme', 'text' => 'Deine Aufnahme', 'spiegel' => 'lb01-u01-f3'],
                ['t' => 'mitnehmen', 'text' => 'Nimm ihn mit'],
                ['t' => 'praxis'],
            ]]]],
        ]]));

        $this->lea = Tenant::create(['slug' => 'lea', 'name' => 'Lea', 'settings' => ['import' => ['wordpress' => [
            'club_product_id' => 1524, 'program_types' => ['1849' => 'hybrid'], 'workbook_dir' => $this->dir,
        ]]]]);
        $this->anna = User::factory()->create();
        $this->bea = User::factory()->create();
        $this->lea->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'legacy_id' => '21']);
        $this->lea->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active', 'legacy_id' => '22']);

        // Kurse
        $this->wpPost(1849, 'kurs', 'Hybrid-Coaching', 'hybrid', "Zwölf Wochen.\n\nEin Weg.", ['untertitel' => 'Neun Schritte', 'kursfarbe' => '#B4795F', 'produkt_id' => '1879', 'lea_produkte' => '1879', 'zugang_tage' => '0', 'kursicon' => 'fa-compass']);
        $this->wpPost(1109, 'kurs', 'Weniger Sorgen', 'weniger-sorgen', '', ['untertitel' => 'Geld', 'produkt_id' => '1518', 'lea_produkte' => '1518,1524', 'im_club' => '1', 'nur_intern' => '1', 'arbeitsbuch' => 'geld', 'reihenfolge' => '1']);
        $this->wpPost(2645, 'kurs', '1:1 Begleitung Anna', '11-anna', '', ['kurs_art' => 'einzel', 'einzel_person' => '21', 'start_datum' => (string) strtotime('2026-09-01')]);
        $this->wpPost(1113, 'kurs', 'Weg', 'weg', '', [], 'trash');
        // Module und Lektionen
        $this->wpPost(100, 'modul', 'Schritt 1: Boden', 'boden', 'Intro von Lea', ['reihenfolge' => '1', 'schritt_nr' => '1']);
        $this->wpPost(101, 'modul', 'Schritt 2: Rad', 'rad', '', ['reihenfolge' => '2']);
        $this->wpPost(200, 'lektion', 'Willkommen', 'willkommen', '<p>Text</p>', ['reihenfolge' => '1', 'info' => 'Kurz vorab', 'videos' => serialize([['video_url' => 'https://vimeo.com/1/abc', 'video_titel' => 'Intro'], ['video_url' => '', 'video_titel' => 'leer']]), 'todos' => '<ul><li>Video schauen</li><li>Frage notieren</li></ul>', 'links' => serialize([['link_url' => 'https://example.com', 'link_text' => 'Beispiel']])]);
        $this->wpPost(201, 'lektion', 'Vertiefung', 'vertiefung', '', ['reihenfolge' => '2']);
        DB::connection('wordpress')->table('jet_rel_default')->insert([
            ['rel_id' => '9', 'parent_object_id' => 1849, 'child_object_id' => 100],
            ['rel_id' => '9', 'parent_object_id' => 1849, 'child_object_id' => 101],
            ['rel_id' => '10', 'parent_object_id' => 100, 'child_object_id' => 200],
            ['rel_id' => '10', 'parent_object_id' => 100, 'child_object_id' => 201],
            ['rel_id' => '13', 'parent_object_id' => 22, 'child_object_id' => 1849],
        ]);
        // Zugaenge, Fortschritt, Antworten
        $this->umeta(21, 'lea_zugaenge', serialize([1849 => ['bis' => 0, 'quelle' => 'kauf', 'ab' => strtotime('2026-09-01'), 'order' => 2757], 1109 => ['bis' => strtotime('+1 year'), 'quelle' => 'abo', 'ab' => strtotime('2026-09-01'), 'order' => 0]]));
        $this->umeta(22, 'lea_zugaenge', serialize([1109 => ['bis' => 0, 'quelle' => 'hand', 'ab' => 0, 'order' => 0]]));
        $this->umeta(21, 'je_data_store_erledigt', serialize([200, 999]));
        $this->umeta(21, 'lea_todos_200', serialize([1]));
        $this->umeta(21, 'lea_wb_antworten', serialize(['s01-u01-f2' => 'Mehr Ruhe', 's01-u01-f4' => '7', 's01-u01-f5' => 'Mut|Klarheit']));
        $this->umeta(21, 'lea_wb_geteilt', 'alles');
        $this->umeta(22, 'lea_wb_antworten', serialize(['s01-u01-f2' => 'Privat']));
        $this->umeta(22, 'lea_wb_antworten_geld', serialize(['g01-u01-f1' => '500']));
        $this->umeta(21, 'lea_wb_antworten_liebesbrief', serialize(['lb01-u01-f1' => "Mehr Ruhe\nMehr Mut\n", 'lb01-u01-f2' => "Ich bin zu laut :: ich bin lebendig\nIch bin zu viel", 'lb01-u01-f3' => 'Liebe Anna, du darfst.']));

        $this->umeta(22, 'lea_wb_geteilt_geld', serialize(['g01-u01' => time()]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    protected function wpPost(int $id, string $type, string $title, string $name, string $content, array $meta, string $status = 'publish'): void
    {
        DB::connection('wordpress')->table('posts')->insert(['ID' => $id, 'post_type' => $type, 'post_title' => $title, 'post_name' => $name, 'post_content' => $content, 'post_status' => $status]);
        foreach ($meta as $k => $v) {
            DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        }
    }

    protected function umeta(int $uid, string $k, string $v): void
    {
        DB::connection('wordpress')->table('usermeta')->insert(['user_id' => $uid, 'meta_key' => $k, 'meta_value' => $v]);
    }

    protected function import(): array
    {
        return app(CurrentTenant::class)->run($this->lea, fn () => (new ProgramsImport($this->lea, new WordPressSource))->run());
    }

    public function test_kurse_module_lektionen_und_arbeitsbuecher(): void
    {
        $stats = $this->import();

        app(CurrentTenant::class)->run($this->lea, function () use ($stats) {
            $this->assertSame(4 + 2, $stats['programme'], '3 Kurse + Workbook und Liebesbrief (eigene) + Geldbuch (im Kurs)');
            $hybrid = Program::where('legacy_id', '1849')->first();
            $this->assertSame('hybrid', $hybrid->type);
            $this->assertSame('weekly', $hybrid->pacing);
            $this->assertSame('hybrid', $hybrid->slug);
            $this->assertSame('compass', $hybrid->icon);
            $this->assertStringContainsString('<p>Zwölf Wochen.</p>', $hybrid->description);
            $this->assertNull(Program::where('legacy_id', '1113')->first(), 'Papierkorb nicht importiert');

            $this->assertSame(['Schritt 1: Boden', 'Schritt 2: Rad'], $hybrid->steps->pluck('title')->all());
            $this->assertSame('<p>Intro von Lea</p>', $hybrid->steps->first()->summary);
            $unit = Unit::where('legacy_id', '200')->first();
            $this->assertSame('Willkommen', $unit->title);
            $this->assertSame([['url' => 'https://vimeo.com/1/abc', 'title' => 'Intro']], $unit->videos);
            $this->assertSame('Kurz vorab', $unit->intro);
            $this->assertSame(['Video schauen', 'Frage notieren'], $unit->exercises->pluck('prompt')->all());
            $this->assertSame('checkbox', $unit->exercises->first()->type);

            $einzel = Program::where('legacy_id', '2645')->first();
            $this->assertSame('one_on_one', $einzel->type);
            $this->assertSame('none', $einzel->pacing);
            $this->assertSame('2026-09-01', $einzel->starts_at->toDateString());

            // Eigenstaendiges Workbook
            $wb = Program::where('legacy_id', 'buch-workbook')->first();
            $this->assertSame('workbook', $wb->type);
            $this->assertSame('Boden', $wb->steps->first()->title);
            $uebung = $wb->units->first();
            $this->assertSame('Deine Intention', $uebung->title);
            $this->assertTrue($uebung->is_core);
            $this->assertSame(['hint', 'text', 'heading', 'scale', 'values'], $uebung->exercises->pluck('type')->all());
            $this->assertSame(['Mut', 'Ruhe', 'Klarheit'], $uebung->exercises->firstWhere('type', 'values')->options['values']);
            $this->assertSame('Energie', $uebung->exercises->firstWhere('type', 'scale')->title);

            // Geldbuch haengt am Kurs 1109
            $geld = Program::where('legacy_id', '1109')->first();
            $this->assertTrue($geld->is_internal);
            $this->assertSame(['Start'], $geld->steps->pluck('title')->all());
            $this->assertSame('Kontostand', $geld->units->first()->title);
        });
    }

    public function test_fortschritt_antworten_und_zugaenge(): void
    {
        $this->import();

        app(CurrentTenant::class)->run($this->lea, function () {
            $unit = Unit::where('legacy_id', '200')->first();
            $this->assertNotNull(Progress::where('user_id', $this->anna->id)->where('unit_id', $unit->id)->whereNotNull('completed_at')->first());
            $haken = Answer::where('user_id', $this->anna->id)->whereIn('exercise_id', $unit->exercises->pluck('id'))->get();
            $this->assertCount(1, $haken);
            $this->assertSame('Frage notieren', $haken->first()->exercise->prompt);

            $wb = Program::where('legacy_id', 'buch-workbook')->first();
            $annaAnswers = Answer::where('user_id', $this->anna->id)->whereIn('exercise_id', $wb->units->first()->exercises->pluck('id'))->get()->keyBy(fn ($a) => $a->exercise->legacy_key);
            $this->assertSame('Mehr Ruhe', $annaAnswers['s01-u01-f2']->value['v']);
            $this->assertSame(7, $annaAnswers['s01-u01-f4']->value['v']);
            $this->assertSame(['Mut', 'Klarheit'], $annaAnswers['s01-u01-f5']->value['v']);
            $this->assertTrue($annaAnswers['s01-u01-f2']->shared_with_coach, 'alles geteilt');
            $this->assertSame('alles', ProgramMember::where('program_id', $wb->id)->where('user_id', $this->anna->id)->value('share_mode'));

            $beaAnswer = Answer::where('user_id', $this->bea->id)->whereIn('exercise_id', $wb->units->first()->exercises->pluck('id'))->first();
            $this->assertFalse($beaAnswer->shared_with_coach);
            $geld = Program::where('legacy_id', '1109')->first();
            $beaGeld = Answer::where('user_id', $this->bea->id)->whereIn('exercise_id', $geld->units->first()->exercises->pluck('id'))->first();
            $this->assertSame('500', $beaGeld->value['v']);
            $this->assertTrue($beaGeld->shared_with_coach, 'einzeln geteilt');

            // Zugaenge
            $hybrid = Program::where('legacy_id', '1849')->first();
            $offer = Offer::where('legacy_id', 'kurs-1849')->first();
            $this->assertSame(['1879'], $offer->products->pluck('external_id')->all());
            $this->assertNull($offer->access_days, 'zugang_tage 0 = unbegrenzt');
            $this->assertSame(365, Offer::where('legacy_id', 'kurs-1109')->first()->access_days);
            $kauf = Entitlement::where('user_id', $this->anna->id)->where('offer_id', $offer->id)->first();
            $this->assertSame('2757', $kauf->source_ref);
            $this->assertNull($kauf->ends_at);
            $club = Offer::where('legacy_id', 'club')->first();
            $this->assertSame(['1524'], $club->products->pluck('external_id')->all());
            $this->assertTrue($club->programs->contains($geld));
            $this->assertNotNull(Entitlement::where('user_id', $this->anna->id)->where('offer_id', $club->id)->where('source_ref', 'abo')->first());

            $access = app(ProgramAccess::class);
            $this->assertTrue($access->canView($this->anna, $hybrid), 'kauf');
            $this->assertTrue($access->canView($this->anna, Program::where('legacy_id', '2645')->first()), 'einzel_person');
            $this->assertTrue($access->canView($this->bea, $hybrid), 'Relation 13');
            $this->assertTrue($access->canView($this->bea, $geld), 'hand -> Mitglied, auch wenn intern');
            $this->assertFalse($access->canView($this->anna, $geld), 'abo gibt keinen Zugang auf interne Kurse');
        });
    }

    public function test_arbeitsbuch_bausteine_antworten_freigabe_und_haken(): void
    {
        // Cara hat im alten Bereich "alles teilen" gewaehlt und eine Uebung selbst abgehakt
        $cara = User::factory()->create();
        $this->lea->users()->attach($cara, ['role' => Role::Member->value, 'status' => 'active', 'legacy_id' => '23']);
        $this->umeta(23, 'lea_wb_antworten', serialize(['s01-u01-f2' => 'Mehr Mut']));
        $this->umeta(23, 'lea_wb_freigabe', 'alles');
        $this->umeta(23, 'lea_wb_antworten_erledigt', serialize(['s01-u01']));
        $this->import();

        app(CurrentTenant::class)->run($this->lea, function () use ($cara) {
            $buch = Program::where('legacy_id', 'buch-liebesbrief')->first();
            $ex = Exercise::whereIn('unit_id', $buch->units()->pluck('id'))->get()->keyBy('legacy_key');
            $this->assertSame(['list', 'pairs', 'letter'], [$ex['lb01-u01-f1']->type, $ex['lb01-u01-f2']->type, $ex['lb01-u01-f3']->type]);
            $this->assertSame('Ich wuensche mir ...', $ex['lb01-u01-f1']->options['platzhalter']);
            $this->assertSame(18, $ex['lb01-u01-f3']->options['zeilen']);
            $this->assertSame(['mirror', 'audio', 'takeaway', 'practice'], [$ex['lb02-u01-f1']->type, $ex['lb02-u01-f2']->type, $ex['lb02-u01-f3']->type, $ex['lb02-u01-f4']->type]);
            $this->assertSame($ex['lb01-u01-f1']->id, $ex['lb02-u01-f1']->options['exercise_id'], 'Spiegel zeigt auf die Liste');
            $this->assertSame($ex['lb01-u01-f3']->id, $ex['lb02-u01-f2']->options['exercise_id'], 'Aufnahme liest den Brief vor');
            $this->assertSame(21, $ex['lb02-u01-f4']->options['tage']);

            $a = Answer::where('user_id', $this->anna->id)->get()->keyBy('exercise_id');
            $this->assertSame(['Mehr Ruhe', 'Mehr Mut'], $a[$ex['lb01-u01-f1']->id]->value['v']);
            $this->assertSame([['Ich bin zu laut', 'ich bin lebendig'], ['Ich bin zu viel', '']], $a[$ex['lb01-u01-f2']->id]->value['v']);
            $this->assertSame('Liebe Anna, du darfst.', $a[$ex['lb01-u01-f3']->id]->value['v']);

            // Cara: "alles teilen" aus lea_wb_freigabe, Erledigt-Haken wird Fortschritt
            $wb = Program::where('legacy_id', 'buch-workbook')->first();
            $this->assertSame('alles', ProgramMember::where('program_id', $wb->id)->where('user_id', $cara->id)->value('share_mode'));
            $this->assertTrue(Answer::where('user_id', $cara->id)->whereHas('exercise', fn ($q) => $q->where('legacy_key', 's01-u01-f2'))->first()->shared_with_coach);
            $unit = Unit::where('legacy_id', 'wb-workbook-s01-u01')->first();
            $this->assertTrue(Progress::where('user_id', $cara->id)->where('unit_id', $unit->id)->whereNotNull('completed_at')->exists());
        });
    }

    public function test_import_ist_wiederholbar(): void
    {
        $this->import();
        app(CurrentTenant::class)->run($this->lea, fn () => Program::where('legacy_id', '1849')->first()->update(['slug' => 'mein-hybrid']));
        $this->import();

        app(CurrentTenant::class)->run($this->lea, function () {
            $this->assertSame(5, Program::count(), '3 Kurse + eigenstaendiges Workbook und Liebesbrief');
            $this->assertSame('mein-hybrid', Program::where('legacy_id', '1849')->first()->slug, 'Slug bleibt');
            $this->assertSame(2, Unit::where('program_id', Program::where('legacy_id', '1849')->first()->id)->count());
            $this->assertSame(1, Entitlement::where('user_id', $this->anna->id)->where('source_ref', '2757')->count());
            $this->assertSame(2, Offer::where('legacy_id', 'club')->first()->products()->count() + Offer::where('legacy_id', 'kurs-1849')->first()->products()->count());
        });
    }
}

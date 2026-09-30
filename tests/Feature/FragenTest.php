<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Question;
use App\Models\QuestionState;
use App\Models\Reaction;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Runden;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FragenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected User $fremd;

    protected Program $kurs;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->fremd = User::factory()->create(['name' => 'Fremd']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->b->users()->attach($this->fremd, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->kurs = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'type' => 'hybrid', 'pacing' => 'weekly']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->bea->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    /** Push-Abo, damit leise Hinweise (ohne Mail) ueberhaupt einen Kanal haben. */
    protected function push(User ...$users): void
    {
        foreach ($users as $u) {
            $this->in(fn () => PushSubscription::create(['user_id' => $u->id, 'endpoint' => 'https://push.example/'.$u->id, 'endpoint_hash' => hash('sha256', 'https://push.example/'.$u->id), 'p256dh' => 'x', 'auth' => 'y']));
        }
    }

    public function test_frage_stellen_beantworten_status_und_callwunsch(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Wie visualisiere ich meine Szene?', 'body' => 'Ich sehe nichts.', 'visibility' => 'program'])->assertRedirect();
        $f = $this->in(fn () => Question::first());
        Notification::assertSentTo($this->lea, AppNotification::class);

        // Bea sieht die Frage im Kurs und wuenscht sie sich fuer den Call
        $this->actingAs($this->bea)->get('http://a.test/kurse/hybrid/fragen')->assertOk()->assertSee('Wie visualisiere ich meine Szene?')->assertSee('Offen');
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/call")->assertRedirect();
        $this->actingAs($this->bea)->get('http://a.test/kurse/hybrid/fragen')->assertSee('1× für den Call gewünscht');

        // Lea antwortet: Status wird "beantwortet", Anna bekommt Bescheid
        $this->actingAs($this->lea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Stell dir einen Morgen vor.'])->assertRedirect();
        $this->assertSame('beantwortet', $f->fresh()->status);
        Notification::assertSentTo($this->anna, AppNotification::class);
        $this->actingAs($this->anna)->get("http://a.test/fragen/{$f->id}")->assertOk()->assertSee('Stell dir einen Morgen vor.')->assertSee('Team');

        // Nur Verwaltende setzen den Status
        $this->actingAs($this->anna)->post("http://a.test/fragen/{$f->id}/status", ['status' => 'zu'])->assertForbidden();
        $this->actingAs($this->lea)->post("http://a.test/fragen/{$f->id}/status", ['status' => 'call'])->assertRedirect();
        $this->assertSame('call', $f->fresh()->status);
    }

    public function test_nur_fuer_die_coachin_und_mandanten_getrennt(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Private Frage', 'visibility' => 'coach']);
        $f = $this->in(fn () => Question::first());

        $this->actingAs($this->bea)->get('http://a.test/kurse/hybrid/fragen')->assertDontSee('Private Frage');
        $this->actingAs($this->bea)->get("http://a.test/fragen/{$f->id}")->assertForbidden();
        $this->actingAs($this->lea)->get("http://a.test/fragen/{$f->id}")->assertOk()->assertSee('Private Frage');

        // Mandant B sieht nichts von A
        $this->actingAs($this->fremd)->get("http://b.test/fragen/{$f->id}")->assertNotFound();
        app(CurrentTenant::class)->run($this->b, fn () => $this->assertSame(0, Question::count()));
    }

    public function test_sammelmail_am_sammeltag_an_das_team(): void
    {
        $this->in(fn () => Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->anna->id, 'title' => 'Offene Frage']));
        $this->travelTo(now()->next('Thursday')->setTime(17, 0));
        $n = $this->in(fn () => app(Runden::class)->fragenSammelmail());
        $this->assertSame(1, $n);
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($x) => str_contains($x->nachricht->text, 'Offene Frage'));

        $this->travelTo(now()->next('Friday'));
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->fragenSammelmail()));
    }

    public function test_antwort_auf_antwort_beste_antwort_herz_und_bearbeiten(): void
    {
        $f = $this->in(fn () => Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->anna->id, 'title' => 'Wie fange ich an?']));
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => "Klein anfangen.\nMehr unter https://example.org/start?x=1."])->assertRedirect();
        $eltern = $this->in(fn () => Comment::where('commentable_type', 'question')->first());

        // Anna antwortet auf Beas Antwort (eine Ebene), Bea erfaehrt es
        Notification::fake();
        $this->actingAs($this->anna)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Danke @Bea, das hilft.', 'parent_id' => $eltern->id])->assertRedirect();
        $kind = $this->in(fn () => Comment::where('parent_id', $eltern->id)->first());
        $this->assertNotNull($kind);
        Notification::assertSentTo($this->bea, AppNotification::class, fn ($x) => str_contains($x->nachricht->titel, 'auf deine Antwort'));
        // Auf ein Kind kann man nicht antworten
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Noch tiefer', 'parent_id' => $kind->id])->assertStatus(422);

        // Seite: Link klickbar, Erwaehnung markiert, Kind unter dem Elternteil, Antwortformular mit Namensvorschlaegen
        $seite = $this->actingAs($this->anna)->get("http://a.test/fragen/{$f->id}")->assertOk();
        $seite->assertSee('href="https://example.org/start?x=1"', false)->assertSee('<span class="erwaehnt">@Bea</span>', false)
            ->assertSee('antwort-kinder')->assertSee('data-erwaehnen=', false)->assertSee('Bea Beispiel');

        // Herz an Beas Antwort: Bea erfaehrt es, Anna kann nicht zweimal
        $this->actingAs($this->anna)->post("http://a.test/reaktion/comment/{$eltern->id}", ['emoji' => 'herz'])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Reaction::where('reactable_type', 'comment')->where('reactable_id', $eltern->id)->count()));
        Notification::assertSentTo($this->bea, AppNotification::class, fn ($x) => str_contains($x->nachricht->titel, 'auf deine Antwort'));
        $this->actingAs($this->anna)->post("http://a.test/reaktion/comment/{$eltern->id}", ['emoji' => 'ja'])->assertStatus(422);
        $this->actingAs($this->bea)->post("http://a.test/reaktion/comment/{$eltern->id}", ['emoji' => 'herz'])->assertStatus(422);

        // Reaktion an der Frage
        $this->actingAs($this->bea)->post("http://a.test/reaktion/question/{$f->id}", ['emoji' => 'auchich'])->assertRedirect();
        $this->actingAs($this->bea)->post("http://a.test/reaktion/question/{$f->id}", ['emoji' => 'stark'])->assertStatus(422);

        // Lea markiert die beste Antwort, die Frage gilt als beantwortet
        $this->actingAs($this->anna)->post("http://a.test/antworten/{$eltern->id}/beste")->assertForbidden();
        $this->actingAs($this->lea)->post("http://a.test/antworten/{$eltern->id}/beste")->assertRedirect();
        $this->assertTrue($eltern->fresh()->is_best);
        $this->assertSame('beantwortet', $f->fresh()->status);
        $this->actingAs($this->anna)->get("http://a.test/fragen/{$f->id}")->assertSee('Das ist die Antwort');

        // Bearbeiten: Bea 15 Minuten lang, danach nicht mehr; Lea immer
        $this->actingAs($this->bea)->patch("http://a.test/antworten/{$eltern->id}", ['body' => 'Klein anfangen, wirklich.'])->assertRedirect();
        $this->assertSame('Klein anfangen, wirklich.', $eltern->fresh()->body);
        $this->assertNotNull($eltern->fresh()->edited_at);
        $this->travel(20)->minutes();
        $this->actingAs($this->bea)->patch("http://a.test/antworten/{$eltern->id}", ['body' => 'Zu spaet'])->assertForbidden();
        $this->actingAs($this->lea)->patch("http://a.test/antworten/{$eltern->id}", ['body' => 'Vom Team geglaettet.'])->assertRedirect();

        // Loeschen des Elternteils macht das Kind zur eigenen Antwort
        $this->actingAs($this->lea)->delete("http://a.test/antworten/{$eltern->id}")->assertRedirect();
        $this->assertNull($kind->fresh()->parent_id);
    }

    public function test_abgeschlossene_frage_nimmt_keine_antworten_mehr(): void
    {
        $f = $this->in(fn () => Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->anna->id, 'title' => 'Erledigt?', 'status' => 'zu']));
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Noch was'])->assertRedirect();
        $this->assertSame(0, $this->in(fn () => Comment::count()));
        $this->actingAs($this->bea)->get("http://a.test/fragen/{$f->id}")->assertSee('abgeschlossen')->assertDontSee('id="antworten"', false);
    }

    public function test_folgen_stumm_und_erwaehnung_steuern_die_meldungen(): void
    {
        $carla = User::factory()->create(['name' => 'Carla Dritte']);
        $this->a->users()->attach($carla, ['role' => Role::Member->value, 'status' => 'active']);
        $this->in(fn () => ProgramMember::create(['program_id' => $this->kurs->id, 'user_id' => $carla->id]));
        $f = $this->in(fn () => Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->anna->id, 'title' => 'Wer weiss Rat?']));
        $this->push($this->anna, $this->bea, $carla);

        // Carla folgt, Anna (die Fragestellerin) schaltet stumm
        $this->actingAs($carla)->post("http://a.test/fragen/{$f->id}/folgen", ['was' => 'folgen'])->assertRedirect();
        $this->actingAs($this->anna)->post("http://a.test/fragen/{$f->id}/folgen", ['was' => 'stumm'])->assertRedirect();
        Notification::fake();
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Ich.'])->assertRedirect();
        Notification::assertSentTo($carla, AppNotification::class);
        Notification::assertSentTo($this->lea, AppNotification::class);
        Notification::assertNotSentTo($this->anna, AppNotification::class);

        // Erwaehnung erreicht auch, wer sonst nichts bekaeme
        $dora = User::factory()->create(['name' => 'Dora Vierte']);
        $this->a->users()->attach($dora, ['role' => Role::Member->value, 'status' => 'active']);
        $this->in(fn () => ProgramMember::create(['program_id' => $this->kurs->id, 'user_id' => $dora->id]));
        $this->push($dora);
        Notification::fake();
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => '@Dora, du hattest das doch auch?'])->assertRedirect();
        Notification::assertSentTo($dora, AppNotification::class, fn ($x) => str_contains($x->nachricht->titel, 'hat dich erwähnt'));
        Notification::assertNotSentTo($this->anna, AppNotification::class);

        // Neue Frage im Kurs: die Gruppe bekommt einen leisen Hinweis, wer es abgeschaltet hat nicht
        $this->in(fn () => $dora->membershipIn()->forceFill(['settings' => ['notifications' => ['fragen' => false]]])->save());
        Notification::fake();
        $this->actingAs($carla)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Neue Frage an alle', 'visibility' => 'program'])->assertRedirect();
        Notification::assertSentTo($this->bea, AppNotification::class, fn ($x) => str_contains($x->nachricht->titel, 'Neue Frage im Kurs'));
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($x) => str_contains($x->nachricht->titel, 'hat eine Frage gestellt'));
        Notification::assertNotSentTo($dora, AppNotification::class);
        // Nur fuer die Coachin: die Gruppe erfaehrt nichts
        Notification::fake();
        $this->actingAs($carla)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Nur Lea', 'visibility' => 'coach'])->assertRedirect();
        Notification::assertNotSentTo($this->bea, AppNotification::class);
        Notification::assertSentTo($this->lea, AppNotification::class);
    }

    public function test_liste_mit_suche_filter_meine_und_neue_antworten(): void
    {
        [$f1, $f2] = $this->in(fn () => [
            Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->anna->id, 'title' => 'Atem im Alltag']),
            Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->bea->id, 'title' => 'Schlaf und Traum']),
        ]);
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?q=Schlaf')->assertOk()->assertSee('Schlaf und Traum')->assertDontSee('Atem im Alltag');
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?f=meine')->assertOk()->assertSee('Atem im Alltag')->assertDontSee('Schlaf und Traum');
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?f=neu')->assertOk()->assertDontSee('Atem im Alltag')->assertDontSee('Schlaf und Traum');

        // Bea antwortet auf Annas Frage: fuer Anna "Neue Antworten", nach dem Besuch nicht mehr
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f1->id}/antworten", ['body' => 'Morgens am Fenster.']);
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?f=neu')->assertOk()->assertSee('Atem im Alltag')->assertSee('Neue Antworten</span>', false);
        $this->actingAs($this->anna)->get("http://a.test/fragen/{$f1->id}")->assertOk()->assertSee('Morgens am Fenster.');
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?f=neu')->assertOk()->assertDontSee('Atem im Alltag');
        $this->assertNotNull($this->in(fn () => QuestionState::where('question_id', $f1->id)->where('user_id', $this->anna->id)->value('seen_at')));

        // Nachladen: fuer Anna gibt es seit Id 0 eine neue Antwort von Bea
        $this->actingAs($this->anna)->getJson("http://a.test/fragen/{$f1->id}/neu?seit=0")->assertOk()->assertJsonPath('anzahl', 1);
        $this->actingAs($this->bea)->getJson("http://a.test/fragen/{$f1->id}/neu?seit=0")->assertOk()->assertJsonPath('anzahl', 0);

        // Sortierung nach Antworten und Community mit Suche
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?sort=antworten')->assertOk()->assertSeeInOrder(['Atem im Alltag', 'Schlaf und Traum']);
        $this->actingAs($this->anna)->get('http://a.test/community?q=Traum')->assertOk()->assertSee('Schlaf und Traum')->assertDontSee('Atem im Alltag');
    }

    public function test_frage_aus_der_community_und_vorbefuellt_aus_einer_lektion(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/community/fragen', ['program_id' => $this->kurs->id, 'title' => 'Aus der Community', 'visibility' => 'program'])->assertRedirect();
        $this->assertSame($this->kurs->id, $this->in(fn () => Question::first()->program_id));
        $this->actingAs($this->anna)->post('http://a.test/community/fragen', ['program_id' => 999, 'title' => 'Falscher Kurs'])->assertStatus(422);

        // Vorbefuellt (Frage dazu aus Uebung, Impuls, Folge): Titel, Text, Anhang; Formular offen
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid/fragen?frage=1&titel=Frage+zu+%C2%ABAtmen%C2%BB&text=Hallo&ref[]=unit:1')->assertOk()
            ->assertSee('value="Frage zu «Atmen»"', false)->assertSee('>Hallo</textarea>', false)->assertSee('<details id="neu" class="baustein" open>', false);
        $this->actingAs($this->anna)->get('http://a.test/community?frage=1&titel=Frage+zum+Impuls')->assertOk()->assertSee('value="Frage zum Impuls"', false);
    }

    public function test_fragentag_push_um_neun_an_den_kurs(): void
    {
        $this->travelTo(now()->next('Thursday')->setTime(8, 30));
        $this->push($this->anna, $this->bea);
        $ev = $this->in(fn () => Event::create(['program_id' => $this->kurs->id, 'title' => 'Fragentag', 'type' => 'question_day', 'all_day' => true, 'is_published' => true,
            'starts_at' => now()->startOfDay()->utc(), 'ends_at' => now()->endOfDay()->utc()]));
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->terminErinnerungen()));

        $this->travelTo(now()->setTime(9, 5));
        Notification::fake();
        $this->assertSame(2, $this->in(fn () => app(Runden::class)->terminErinnerungen()));
        Notification::assertSentTo($this->anna, AppNotification::class, fn ($x, $channels) => $x->nachricht->titel === 'Heute ist Fragentag' && str_contains($x->nachricht->url, '/kurse/hybrid/fragen') && ! in_array('mail', $channels, true));
        $this->assertNotNull($ev->fresh()->reminded_day_at);
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->terminErinnerungen()));

        // Startseite und Kursschritt fuehren zu den Kursfragen, nicht in den Chat
        $this->in(fn () => $this->anna->membershipIn()->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save());
        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertSee('/kurse/hybrid/fragen?frage=1');
    }
}

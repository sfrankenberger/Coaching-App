<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Entitlement;
use App\Models\Event;
use App\Models\Offer;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\Resource;
use App\Models\Resourceable;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Runden;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Meine Kurse als Schaufenster, Neu-Punkte im Menue, Einzelsitzungen in Hybrid-Kursen, Antworten in "Was ist neu". */
class SchaufensterTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected Program $hybrid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->anna->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        $this->hybrid = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid', 'pacing' => 'weekly', 'settings' => ['sitzungen_gesamt' => 3]]);
            $w = $p->steps()->create(['title' => 'Woche 1', 'position' => 1, 'unlocks_at' => now()->subDay()]);
            $p->units()->create(['title' => 'Start', 'step_id' => $w->id, 'position' => 1]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            Event::create(['program_id' => $p->id, 'title' => 'Gruppencall', 'starts_at' => now()->addDays(2)->setTime(19, 0)]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_meine_kurse_mit_call_zugang_bis_gesperrten_angeboten_und_kommt_bald(): void
    {
        $this->in(function () {
            $club = Program::create(['slug' => 'club', 'title' => 'Der Club', 'type' => 'club', 'pacing' => 'self_paced']);
            $club->units()->create(['title' => 'Willkommen im Club', 'position' => 1]);
            $o = Offer::create(['title' => 'Clubzugang', 'slug' => 'club', 'type' => 'membership', 'is_active' => true, 'settings' => ['sichtbar' => true, 'preis_chf' => 49]]);
            $o->programs()->attach($club->id, ['tenant_id' => $this->a->id]);
            // Zugang zum Hybrid laeuft in 30 Tagen ab
            $oh = Offer::create(['title' => 'Hybrid-Zugang', 'slug' => 'hybrid', 'type' => 'course', 'is_active' => true, 'settings' => ['preis_chf' => 990]]);
            $oh->programs()->attach($this->hybrid->id, ['tenant_id' => $this->a->id]);
            Entitlement::create(['user_id' => $this->anna->id, 'offer_id' => $oh->id, 'status' => 'active', 'ends_at' => now()->addDays(30)]);
            Program::create(['slug' => 'bald', 'title' => 'Der neue Kurs', 'type' => 'selfpaced', 'pacing' => 'self_paced', 'is_published' => false, 'settings' => ['kommt_bald' => true], 'starts_at' => now()->addMonth()]);
        });

        $seite = $this->actingAs($this->anna)->get('http://a.test/kurse')->assertOk();
        $seite->assertSee('Dein Programm')->assertSee('Nächster Call:')->assertSee('Freigeschaltet bis '.now()->addDays(30)->translatedFormat('j. F Y'))
            ->assertSee('Was es noch gibt')->assertSee('Clubzugang')->assertSee('49.00 CHF')->assertSee('/kaufen/club')
            ->assertSee('Der neue Kurs')->assertSee('Kommt bald')->assertDontSee('Willkommen im Club');

        // Hybrid mit Einzelsitzungen: Kontingent auf der Kursseite und im Profil
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid')->assertOk()->assertSee('Einzelsitzungen im Kurs enthalten')->assertSee('von 3 noch offen');
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertSee('Sitzungen: 3 von 3 offen');
    }

    public function test_kurs_ohne_module_zeigt_die_naechsten_termine(): void
    {
        $this->in(function () {
            $c = Program::create(['slug' => 'coffee', 'title' => 'Coffee und Coaching', 'type' => 'club', 'pacing' => 'self_paced']);
            ProgramMember::create(['program_id' => $c->id, 'user_id' => $this->anna->id]);
            foreach ([1, 8, 15] as $d) {
                Event::create(['program_id' => $c->id, 'title' => 'Coffee '.$d, 'starts_at' => now()->addDays($d)->setTime(9, 0)]);
            }
        });
        $this->actingAs($this->anna)->get('http://a.test/kurse/coffee')->assertOk()->assertSee('Nächste Termine')->assertSee('Coffee 1')->assertSee('Coffee 15');
    }

    public function test_neu_punkte_im_menue_und_antworten_in_was_ist_neu(): void
    {
        $this->in(function () {
            Post::create(['title' => 'Neuer Impuls', 'slug' => 'neuer-impuls', 'type' => 'impuls', 'body' => 'Text', 'published_at' => now()->subHour()]);
            $r = Resource::create(['title' => 'Neues Blatt', 'type' => 'link', 'url' => 'https://example.ch/blatt']);
            Resourceable::create(['resource_id' => $r->id, 'resourceable_type' => 'program', 'resourceable_id' => $this->hybrid->id]);
            Question::create(['user_id' => $this->lea->id, 'program_id' => $this->hybrid->id, 'title' => 'Frage der Coachin']);
        });
        // Vor dem Besuch zaehlt das Menue, nach dem Besuch nicht mehr
        $this->actingAs($this->anna)->get('http://a.test/kurse')->assertOk()
            ->assertSee('Impulse <span class="zahl">1</span>', false)->assertSee('Ressourcen <span class="zahl">1</span>', false)
            ->assertSee('Community <span class="zahl">1</span>', false)->assertSee('Termine <span class="zahl">1</span>', false);
        $this->actingAs($this->anna)->get('http://a.test/impulse')->assertOk();
        $this->actingAs($this->anna)->get('http://a.test/community')->assertOk();
        $this->actingAs($this->anna)->get('http://a.test/kurse')->assertOk()
            ->assertDontSee('Impulse <span class="zahl">', false)->assertDontSee('Community <span class="zahl">', false)->assertSee('Ressourcen <span class="zahl">1</span>', false);

        // Lea antwortet auf Annas Frage: steht in "Was ist neu"
        $f = $this->in(fn () => Question::create(['user_id' => $this->anna->id, 'program_id' => $this->hybrid->id, 'title' => 'Meine Frage']));
        $this->actingAs($this->lea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Gute Frage, Anna.'])->assertRedirect();
        $neues = $this->in(fn () => app(Runden::class)->neuesFuer($this->anna, now()->subDay()));
        $this->assertTrue($neues->contains(fn ($n) => $n['titel'] === 'Lea hat auf deine Frage geantwortet' && str_contains($n['url'], "/fragen/{$f->id}#antwort-")));
        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertSee('Lea hat auf deine Frage geantwortet');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FinderProfile;
use App\Models\Message;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Sammlung;
use App\Models\SearchHistory;
use App\Models\Tenant;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NachschlagenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected Program $mein;

    protected Program $fremd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea', 'shop.url' => 'https://shop.test']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = $this->person('Lea Coach', Role::Owner);
        $this->anna = $this->person('Anna Muster', Role::Member);
        $this->in(function () {
            $this->mein = Program::create(['slug' => 'mein', 'title' => 'Geld und Freiheit']);
            $this->fremd = Program::create(['slug' => 'fremd', 'title' => 'Beziehung leben', 'settings' => ['sales_url' => 'https://shop.test/beziehung']]);
            ProgramMember::create(['program_id' => $this->mein->id, 'user_id' => $this->anna->id]);
            $u1 = Unit::create(['program_id' => $this->mein->id, 'title' => 'Die Schneekugel', 'body' => '<p>Wenn alles aufgewirbelt ist.</p>', 'is_published' => true, 'position' => 1]);
            $u2 = Unit::create(['program_id' => $this->fremd->id, 'title' => 'Schuld in der Beziehung', 'body' => 'x', 'is_published' => true, 'position' => 1]);
            $p = Post::create(['title' => 'Impuls: Schneekugel im Kopf', 'slug' => 'schneekugel', 'body' => '<p>Text</p>', 'visibility' => 'members', 'published_at' => now()->subDay()]);
            $t = Topic::create(['name' => 'Gedanken', 'group' => 'Innen']);
            $u1->topics()->attach($t->id);
            $u2->topics()->attach($t->id);
            FinderProfile::create(['profilable_type' => 'unit', 'profilable_id' => $u1->id, 'summary' => 'Wie Gedanken sich beruhigen.', 'helps' => 'Hilft, wenn im Kopf alles durcheinander ist.', 'keywords' => ['ruhe', 'gedanken']]);
            FinderProfile::create(['profilable_type' => 'unit', 'profilable_id' => $u2->id, 'summary' => 'Schuldgefühle in Beziehungen.', 'helps' => 'Hilft, wenn du dich schuldig fühlst.', 'keywords' => ['schuld']]);
            FinderProfile::create(['profilable_type' => 'post', 'profilable_id' => $p->id, 'summary' => 'Ein Bild für Gedankenstürme.', 'helps' => 'Hilft, wenn es stürmt.', 'keywords' => ['sturm']]);
        });
    }

    protected function person(string $name, Role $role): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->a->users()->attach($user, ['role' => $role->value, 'status' => 'active', 'settings' => json_encode(['onboarding_seen_at' => now()->toIso8601String()])]);

        return $user;
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_ein_wort_sucht_direkt_und_gesperrtes_bekommt_eine_tuer(): void
    {
        $r = $this->actingAs($this->anna)->get('http://a.test/nachschlagen?q=Schneekugel')->assertOk();
        $r->assertSee('Die Schneekugel')->assertSee('Schneekugel im Kopf')->assertSee('Hilft, wenn im Kopf');
        $r->assertDontSee('Schuld in der Beziehung');

        // Ueber das Thema kommt auch die gesperrte Lektion, mit Tuer statt Link
        $t = $this->in(fn () => Topic::first());
        $r = $this->actingAs($this->anna)->get('http://a.test/nachschlagen?thema='.$t->id)->assertOk();
        $r->assertSee('Beziehung leben')->assertSee('Diesen Kurs hast du noch nicht')->assertSee('https://shop.test/beziehung');
        $r->assertSee('Geld und Freiheit')->assertSee('Lektion »Die Schneekugel«');

        // Beides steht im Verlauf
        $this->assertSame(2, $this->in(fn () => SearchHistory::where('user_id', $this->anna->id)->count()));
        $this->actingAs($this->anna)->get('http://a.test/nachschlagen?r=verlauf')->assertOk()->assertSee('Schneekugel')->assertSee('Gedanken');
        $this->actingAs($this->anna)->delete('http://a.test/nachschlagen/verlauf')->assertRedirect();
        $this->assertSame(0, $this->in(fn () => SearchHistory::count()));
    }

    public function test_ein_satz_fragt_die_ki_und_die_antwort_hat_ein_warum(): void
    {
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $u = $this->in(fn () => Unit::where('title', 'Die Schneekugel')->first());
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'antwort' => 'Das klingt nach einem vollen Kopf. Schau dir das an.',
            'treffer' => [['id' => 'unit-'.$u->id, 'warum' => 'Genau dafür ist die Übung gemacht.'], ['id' => 'unit-9999', 'warum' => 'gibt es nicht']],
        ])]], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);

        $r = $this->actingAs($this->anna)->get('http://a.test/nachschlagen?q=Mir+geht+alles+durcheinander+im+Kopf')->assertOk();
        $r->assertSee('Das klingt nach einem vollen Kopf')->assertSee('Genau dafür ist die Übung gemacht')->assertSee('Geld und Freiheit');
        Http::assertSent(fn ($req) => str_contains($req->body(), 'Fundus (ID | Art') && str_contains($req->body(), 'aus dem Kurs'));
        $h = $this->in(fn () => SearchHistory::first());
        $this->assertSame('frage', $h->kind);
        $this->assertStringContainsString('vollen Kopf', $h->answer);
    }

    public function test_vorschau_und_die_coachin_schickt_eine_sammlung_in_den_chat(): void
    {
        $u = $this->in(fn () => Unit::where('title', 'Die Schneekugel')->first());
        $this->actingAs($this->anna)->get("http://a.test/nachschlagen/vorschau/unit/{$u->id}")->assertOk()->assertSee('Zur Lektion')->assertSee('Wie Gedanken sich beruhigen');
        $this->actingAs($this->anna)->post('http://a.test/nachschlagen/teilen', ['items' => ['unit-'.$u->id], 'an' => [$this->anna->id]])->assertForbidden();

        $r = $this->actingAs($this->lea)->postJson('http://a.test/nachschlagen/teilen', ['items' => ['unit-'.$u->id], 'name' => 'Für Anna', 'gruss' => 'Das passt zu dir.', 'an' => [$this->anna->id]])->assertOk();
        $this->assertSame(1, $r->json('an'));
        $link = $r->json('link');
        $this->assertStringContainsString('/sammlung/', $link);
        $this->assertSame(1, $this->in(fn () => Message::where('user_id', $this->lea->id)->where('body', 'like', '%Für Anna%')->count()));

        $this->actingAs($this->anna)->get($link)->assertOk()->assertSee('Für Anna')->assertSee('Das passt zu dir.')->assertSee('Die Schneekugel');
        $this->assertContains($this->anna->id, $this->in(fn () => Sammlung::first()->seen));
        $this->actingAs($this->anna)->get(preg_replace('~/[^/]+$~', '/falsch', $link))->assertNotFound();
    }

    public function test_werkzeuge_nur_fuer_die_ausbildung(): void
    {
        $w = $this->in(fn () => Tool::create(['title' => 'Der leere Stuhl', 'purpose' => 'Perspektive wechseln.', 'steps' => "Stuhl hinstellen.\nSprechen.", 'is_published' => true]));
        $this->actingAs($this->anna)->get('http://a.test/werkzeuge')->assertOk()->assertSee('gehören zur Coach-Ausbildung')->assertDontSee('leere Stuhl');
        $this->actingAs($this->anna)->get('http://a.test/werkzeuge/'.$w->slug)->assertForbidden();
        $this->actingAs($this->anna)->get('http://a.test/nachschlagen?q=Stuhl')->assertOk()->assertSee('Der leere Stuhl')->assertSee('Gehört zur Coach-Ausbildung');

        $this->in(fn () => $this->anna->membershipIn()->forceFill(['settings' => ['ausbildung' => true, 'onboarding_seen_at' => now()->toIso8601String()]])->save());
        $this->actingAs($this->anna->fresh())->get('http://a.test/werkzeuge/'.$w->slug)->assertOk()->assertSee('Perspektive wechseln')->assertSee('So geht es');
        $this->actingAs($this->lea)->get('http://a.test/coach/tools')->assertOk()->assertSee('Der leere Stuhl');
    }

    public function test_mandant_b_sieht_nichts_von_a(): void
    {
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $bea = User::factory()->create(['name' => 'Bea']);
        $b->users()->attach($bea, ['role' => Role::Owner->value, 'status' => 'active', 'settings' => json_encode(['onboarding_seen_at' => now()->toIso8601String()])]);
        $this->in(function () {
            Tool::create(['title' => 'Nur in A', 'is_published' => true]);
            Sammlung::create(['user_id' => $this->lea->id, 'title' => 'Sammlung A', 'items' => [], 'key' => 'schluessel']);
            SearchHistory::merken($this->anna, 'Suche A', 'wort', null, []);
        });
        app(CurrentTenant::class)->run($b, function () {
            $this->assertSame(0, Tool::count());
            $this->assertSame(0, Sammlung::count());
            $this->assertSame(0, SearchHistory::count());
        });
        $this->actingAs($bea)->get('http://b.test/nachschlagen?q=Nur')->assertOk()->assertDontSee('Nur in A');
        $this->actingAs($bea)->get('http://b.test/sammlung/1/schluessel')->assertNotFound();
    }
}

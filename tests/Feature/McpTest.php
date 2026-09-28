<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CoachNote;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wissen;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MCP-Server: Claude oder ChatGPT verbinden sich mit Token und nutzen die Werkzeuge. */
class McpTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@test.ch']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->token = $this->lea->createToken('claude', ['lesen', 'mcp'])->plainTextToken;
    }

    protected function rpc(string $method, array $params = [], ?string $token = null, mixed $id = 1)
    {
        // Der Sanctum-Guard merkt sich die Person zwischen zwei Anfragen im selben Test
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    }

    protected function werkzeug(string $name, array $args = [], ?string $token = null): array
    {
        $r = $this->rpc('tools/call', ['name' => $name, 'arguments' => $args], $token)->assertOk()->json('result');
        $this->assertFalse($r['isError'] ?? false, $r['content'][0]['text'] ?? '');

        return json_decode($r['content'][0]['text'], true);
    }

    public function test_handschlag_und_werkzeugliste(): void
    {
        $r = $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])->assertOk();
        $this->assertSame('2025-03-26', $r->json('result.protocolVersion'));
        $this->assertSame('A', $r->json('result.serverInfo.name'));
        $this->assertStringContainsString('Lea', $r->json('result.instructions'));

        $this->withToken($this->token)->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);
        $this->rpc('ping')->assertOk()->assertJsonPath('id', 1);

        $namen = collect($this->rpc('tools/list')->assertOk()->json('result.tools'))->pluck('name');
        foreach (['heute', 'personen_suchen', 'person_fakten', 'person_anlegen', 'zugang_geben', 'angebote', 'nachricht_senden', 'aufgabe_geben', 'termin_anlegen', 'zeiten_vorschlagen', 'termine', 'lage', 'notiz_schreiben', 'inhalte_suchen', 'wissen_suchen', 'wissen_merken'] as $n) {
            $this->assertTrue($namen->contains($n), $n);
        }
        $this->rpc('unbekannt')->assertOk()->assertJsonPath('error.code', -32601);
        $this->withToken($this->token)->get('http://a.test/api/mcp')->assertStatus(405);
    }

    public function test_zugriff_nur_mit_mcp_faehigkeit_und_team(): void
    {
        $this->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertUnauthorized();
        $nurLesen = $this->lea->createToken('api', ['lesen'])->plainTextToken;
        $this->rpc('ping', [], $nurLesen)->assertForbidden();
        $annaToken = $this->anna->createToken('x', ['lesen', 'mcp'])->plainTextToken;
        $this->rpc('ping', [], $annaToken)->assertForbidden();
    }

    public function test_werkzeuge_arbeiten_im_mandanten(): void
    {
        $offer = app(CurrentTenant::class)->run($this->a, function () {
            $p = Program::create(['slug' => 'jk', 'title' => 'Jahreskurs', 'is_published' => true]);
            $o = Offer::create(['title' => 'Jahreskurs', 'access_days' => 365, 'is_active' => true]);
            $o->programs()->attach($p, ['tenant_id' => $this->a->id]);

            return $o;
        });

        $s = $this->werkzeug('personen_suchen', ['suche' => 'anna']);
        $this->assertSame(1, $s['anzahl']);
        $this->assertSame('anna@test.ch', $s['personen'][0]['email']);
        $mid = $s['personen'][0]['membership_id'];

        $f = $this->werkzeug('person_fakten', ['membership_id' => $mid]);
        $this->assertSame('Anna Muster', $f['name']);
        $this->assertStringContainsString('/coachees/'.$mid, $f['dossier']);

        $neu = $this->werkzeug('person_anlegen', ['name' => 'Nora Neu', 'email' => 'nora@test.ch', 'rolle' => 'client', 'notiz' => 'Über Claude angelegt']);
        $this->assertTrue($neu['neu']);
        $this->assertSame('1:1-Kundin', $neu['rolle']);
        $this->assertSame(1, app(CurrentTenant::class)->run($this->a, fn () => CoachNote::whereHas('user', fn ($u) => $u->where('email', 'nora@test.ch'))->count()));

        $z = $this->werkzeug('zugang_geben', ['person' => 'nora@test.ch', 'angebot' => 'Jahreskurs', 'preis' => '990 CHF']);
        $this->assertTrue($z['ok']);
        $this->assertSame(['Jahreskurs'], $z['programme']);
        $f2 = $this->werkzeug('person_fakten', ['person' => 'nora@test.ch']);
        $this->assertTrue(collect($f2['kurse'])->contains(fn ($k) => str_starts_with($k, 'Jahreskurs')), implode(', ', $f2['kurse']));

        $n = $this->werkzeug('nachricht_senden', ['membership_id' => $mid, 'text' => 'Hallo Anna, bis morgen!']);
        $this->assertTrue($n['ok']);
        $this->assertSame($this->lea->id, app(CurrentTenant::class)->run($this->a, fn () => Message::find($n['message_id'])->user_id));

        $t = $this->werkzeug('termin_anlegen', ['membership_id' => $mid, 'start' => now()->addDays(2)->format('Y-m-d').' 10:00', 'dauer' => 30]);
        $this->assertTrue($t['ok']);
        $liste = $this->werkzeug('termine', ['membership_id' => $mid]);
        $this->assertCount(1, $liste);
        $this->assertSame('Anna Muster', $liste[0]['mit']);

        $this->werkzeug('wissen_merken', ['text' => 'Rechnungen schreibt Andrea jeweils am Monatsende.', 'tags' => ['ablauf']]);
        $w = $this->werkzeug('wissen_suchen', ['suche' => 'Rechnungen']);
        $this->assertCount(1, $w);
        $this->assertSame('mcp', $w[0]['quelle']);
        $this->assertSame(1, app(CurrentTenant::class)->run($this->a, fn () => Wissen::count()));

        $h = $this->werkzeug('heute');
        $this->assertCount(1, $h['als_naechstes']);

        // Fachlicher Fehler kommt als isError, nicht als Protokollfehler
        $r = $this->rpc('tools/call', ['name' => 'person_fakten', 'arguments' => ['person' => 'gibtsnicht@test.ch']])->assertOk()->json('result');
        $this->assertTrue($r['isError']);
        $this->assertStringContainsString('Niemand gefunden', $r['content'][0]['text']);

        // Anderer Mandant: Annas Daten sind dort unsichtbar
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $mia = User::factory()->create(['name' => 'Mia B']);
        $b->users()->attach($mia, ['role' => Role::Owner->value, 'status' => 'active']);
        $tb = $mia->createToken('c', ['lesen', 'mcp'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $r = $this->withToken($tb)->postJson('http://b.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call', 'params' => ['name' => 'personen_suchen', 'arguments' => []]])->assertOk();
        $inB = json_decode($r->json('result.content.0.text'), true);
        $this->assertSame(1, $inB['anzahl']);
        $this->assertSame('Mia B', $inB['personen'][0]['name']);
        $this->app['auth']->forgetGuards();
        $r = $this->withToken($tb)->postJson('http://b.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call', 'params' => ['name' => 'wissen_suchen', 'arguments' => []]])->assertOk();
        $this->assertSame([], json_decode($r->json('result.content.0.text'), true));
    }
}

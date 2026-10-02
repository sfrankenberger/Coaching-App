<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\OauthClient;
use App\Models\OauthCode;
use App\Models\OauthRefreshToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** OAuth fuer den MCP-Server: Claude registriert sich, Lea erlaubt, Token mit PKCE, Erneuerung, Mandantentrennung. */
class OAuthMcpTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected string $verifier = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');
    }

    protected function registrieren(string $host = 'a.test'): string
    {
        return $this->postJson("http://$host/oauth/register", ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
            ->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none')->json('client_id');
    }

    protected function freigeben(string $clientId, User $als): string
    {
        $antwort = $this->actingAs($als)->post('http://a.test/oauth/authorize', [
            'client_id' => $clientId, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_challenge' => $this->challenge(), 'state' => 'xyz', 'entscheidung' => 'erlauben',
        ])->assertRedirect();
        parse_str((string) parse_url($antwort->headers->get('Location'), PHP_URL_QUERY), $q);

        return $q['code'] ?? '';
    }

    public function test_beschreibung_und_401_mit_hinweis(): void
    {
        $this->getJson('http://a.test/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('issuer', 'http://a.test')->assertJsonPath('token_endpoint', 'http://a.test/oauth/token')->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $this->getJson('http://a.test/.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('resource', 'http://a.test/api/mcp')->assertJsonPath('authorization_servers.0', 'http://a.test');
        $this->getJson('http://a.test/.well-known/oauth-protected-resource/api/mcp')->assertOk();

        $this->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="http://a.test/.well-known/oauth-protected-resource"');
    }

    public function test_registrieren_freigeben_token_und_mcp_nutzen(): void
    {
        $clientId = $this->registrieren();
        $this->postJson('http://a.test/oauth/register', ['redirect_uris' => ['http://boese.example/x']])->assertStatus(400);

        // Nicht angemeldet: zur Anmeldung, mit Rueckweg
        $this->get('http://a.test/oauth/authorize?client_id='.$clientId.'&redirect_uri=https%3A%2F%2Fclaude.ai%2Fapi%2Fmcp%2Fauth_callback&code_challenge='.$this->challenge().'&code_challenge_method=S256&response_type=code&state=xyz')
            ->assertRedirect()->assertRedirectContains('/anmelden?weiter=');
        // Angemeldet: Freigabeseite
        $this->actingAs($this->lea)->get('http://a.test/oauth/authorize?client_id='.$clientId.'&redirect_uri=https%3A%2F%2Fclaude.ai%2Fapi%2Fmcp%2Fauth_callback&code_challenge='.$this->challenge().'&code_challenge_method=S256&response_type=code&state=xyz')
            ->assertOk()->assertSee('Claude möchte mit der App arbeiten')->assertSee('Erlauben');
        // Falsche Rueckadresse: kein Redirect dorthin
        $this->actingAs($this->lea)->get('http://a.test/oauth/authorize?client_id='.$clientId.'&redirect_uri=https%3A%2F%2Fboese.example%2F&code_challenge='.$this->challenge().'&response_type=code')->assertStatus(400);

        $code = $this->freigeben($clientId, $this->lea);
        $this->assertNotSame('', $code);

        // Falscher Verifier
        $this->postJson('http://a.test/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => 'falsch'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        // Richtig
        $t = $this->postJson('http://a.test/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => $this->verifier])
            ->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('scope', 'mcp')->json();
        // Code ist verbraucht
        $this->postJson('http://a.test/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => $this->verifier])->assertStatus(400);

        $this->app['auth']->forgetGuards();
        $this->withToken($t['access_token'])->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->assertJsonPath('result.tools.0.name', 'heute');
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $this->lea->id)->count());
        $this->assertNotNull(PersonalAccessToken::where('tokenable_id', $this->lea->id)->first()->expires_at);

        // Erneuern: altes Zugangs-Token weg, neues da, alter Erneuerungs-Token widerrufen
        $alt = $t['refresh_token'];
        $n = $this->postJson('http://a.test/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $alt])->assertOk()->json();
        $this->assertNotSame($t['access_token'], $n['access_token']);
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $this->lea->id)->count(), 'altes Token geloescht');
        $this->postJson('http://a.test/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $alt])->assertStatus(400);
        $this->app['auth']->forgetGuards();
        $this->withToken($n['access_token'])->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($t['access_token'])->postJson('http://a.test/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertStatus(401);
    }

    public function test_nur_team_darf_freigeben_und_ablehnen_geht_zurueck(): void
    {
        $clientId = $this->registrieren();
        $this->actingAs($this->anna)->get('http://a.test/oauth/authorize?client_id='.$clientId.'&redirect_uri=https%3A%2F%2Fclaude.ai%2Fapi%2Fmcp%2Fauth_callback&code_challenge='.$this->challenge().'&response_type=code')
            ->assertOk()->assertSee('nur')->assertDontSee('value="erlauben"', false);
        $this->actingAs($this->anna)->post('http://a.test/oauth/authorize', ['client_id' => $clientId, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_challenge' => $this->challenge(), 'state' => 's', 'entscheidung' => 'erlauben'])
            ->assertRedirectContains('error=access_denied');
        $this->assertSame(0, OauthCode::withoutGlobalScopes()->count());
        $this->actingAs($this->lea)->post('http://a.test/oauth/authorize', ['client_id' => $clientId, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_challenge' => $this->challenge(), 'state' => 's', 'entscheidung' => 'ablehnen'])
            ->assertRedirectContains('error=access_denied')->assertRedirectContains('state=s');
    }

    public function test_mandant_b_kennt_client_und_code_von_a_nicht(): void
    {
        $clientId = $this->registrieren('a.test');
        $code = $this->freigeben($clientId, $this->lea);
        $this->assertSame(1, OauthClient::withoutGlobalScopes()->where('tenant_id', $this->a->id)->count());

        $this->postJson('http://b.test/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => $this->verifier])
            ->assertStatus(401)->assertJsonPath('error', 'invalid_client');
        // Lea ist noch angemeldet, aber in B gibt es diesen Client nicht
        $this->get('http://b.test/oauth/authorize?client_id='.$clientId.'&redirect_uri=https%3A%2F%2Fclaude.ai%2Fapi%2Fmcp%2Fauth_callback&code_challenge='.$this->challenge().'&response_type=code')->assertStatus(400);
        $this->assertSame(0, OauthRefreshToken::withoutGlobalScopes()->where('tenant_id', $this->b->id)->count());
    }
}

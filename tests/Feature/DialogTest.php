<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Newsletter;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Claude in der App: Lese-Werkzeuge laufen sofort, Schreib-Werkzeuge erst nach Rueckfrage. */
class DialogTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
    }

    protected function antwort(array $content, string $stop = 'end_turn'): array
    {
        return ['content' => $content, 'stop_reason' => $stop, 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]];
    }

    public function test_lesen_sofort_schreiben_nach_rueckfrage(): void
    {
        $anfragen = [];
        $n = 0;
        Http::fake(function ($r) use (&$anfragen, &$n) {
            $anfragen[] = $r->data();
            $n++;

            return match ($n) {
                1 => Http::response($this->antwort([['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'newsletter_liste', 'input' => []]], 'tool_use')),
                2 => Http::response($this->antwort([['type' => 'text', 'text' => 'Noch kein Newsletter da. Ich lege einen an.'], ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'newsletter_anlegen', 'input' => ['betreff' => 'Herbst', 'text' => 'Hallo {vorname}', 'tags' => ['newsletter']]]], 'tool_use')),
                default => Http::response($this->antwort([['type' => 'text', 'text' => 'Erledigt, der Entwurf steht.']])),
            };
        });

        $this->actingAs($this->lea)->post('http://a.test/assistent/chat', ['text' => 'Leg einen Herbst-Newsletter an'])->assertRedirect('http://a.test/assistent#chat');
        $this->assertSame(2, $n, 'Lese-Werkzeug lief sofort, dann kam die Rueckfrage');
        $this->assertSame('tool_result', $anfragen[1]['messages'][2]['content'][0]['type'], 'Ergebnis des Lese-Werkzeugs ging an Claude');
        $this->assertNotEmpty($anfragen[0]['tools']);
        $this->assertSame(0, $this->in(fn () => Newsletter::count()), 'noch nichts angelegt');

        $seite = $this->actingAs($this->lea)->get('http://a.test/assistent')->assertOk();
        $seite->assertSee('Soll ich das machen?')->assertSee('newsletter anlegen')->assertSee('betreff: Herbst')->assertSee('Ich lege einen an');
        // Weiterschreiben geht erst nach der Antwort
        $this->actingAs($this->lea)->post('http://a.test/assistent/chat', ['text' => 'noch was'])->assertSessionHas('fehler');

        $this->actingAs($this->lea)->post('http://a.test/assistent/chat/entscheiden', ['ja' => 1])->assertRedirect();
        $this->assertSame(3, $n);
        $this->assertSame(1, $this->in(fn () => Newsletter::count()));
        $this->assertSame('Herbst', $this->in(fn () => Newsletter::first()->betreff));
        $this->actingAs($this->lea)->get('http://a.test/assistent')->assertOk()->assertSee('Erledigt, der Entwurf steht.')->assertDontSee('Soll ich das machen?');

        // Abbrechen: nichts passiert, Claude erfaehrt es
        $n = 0;
        $this->actingAs($this->lea)->post('http://a.test/assistent/chat', ['text' => 'Und noch einen'])->assertRedirect();
        $this->actingAs($this->lea)->post('http://a.test/assistent/chat/entscheiden', ['ja' => 0])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Newsletter::count()));
        $letzte = end($anfragen);
        $this->assertStringContainsString('abgebrochen', json_encode($letzte['messages'], JSON_UNESCAPED_UNICODE));

        // Neues Gespraech
        $this->actingAs($this->lea)->post('http://a.test/assistent/chat/neu')->assertRedirect();
        $this->actingAs($this->lea)->get('http://a.test/assistent')->assertOk()->assertDontSee('Erledigt, der Entwurf steht.');
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }
}

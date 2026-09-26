<?php

namespace Tests\Feature;

use App\Ai\Assistent;
use App\Enums\Role;
use App\Filament\Coach\Pages\Assistent as AssistentSeite;
use App\Models\Event;
use App\Models\FinderProfile;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AssistentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $nicole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea', 'ai' => ['wissen' => 'Andrea macht die Rechnungen in bexio.']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->nicole = User::factory()->create(['name' => 'Nicole Beispiel', 'email' => 'nicole@test.ch']);
        $this->a->users()->attach($this->nicole, ['role' => Role::Client->value, 'status' => 'active', 'joined_at' => now()->subMonth()]);
        $nico = User::factory()->create(['name' => 'Nicole Zweit', 'email' => 'nz@test.ch']);
        $this->a->users()->attach($nico, ['role' => Role::Member->value, 'status' => 'active']);
        app(CurrentTenant::class)->run($this->a, function () {
            $p = Program::create(['slug' => 'einzel', 'title' => 'Einzelbegleitung', 'type' => 'one_on_one', 'settings' => ['sitzungen_gesamt' => 5]]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->nicole->id]);
            Event::withoutEvents(fn () => Event::create(['tenant_id' => $this->a->id, 'title' => 'Sitzung 1', 'type' => 'one_on_one', 'user_id' => $this->nicole->id, 'starts_at' => now()->subWeek(), 'is_published' => true]));
            $u = Unit::create(['program_id' => $p->id, 'title' => 'Die Schneekugel', 'body' => 'x', 'is_published' => true, 'position' => 1]);
            FinderProfile::create(['profilable_type' => 'unit', 'profilable_id' => $u->id, 'summary' => 'Gedanken beruhigen.', 'helps' => 'Hilft, wenn es stürmt.', 'keywords' => ['ruhe'], 'is_checked' => false, 'generated_at' => now()]);
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_betriebsfrage_erkennt_die_person_und_liefert_fakten(): void
    {
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Nicole hat die Einzelbegleitung, 4 von 5 Sitzungen sind offen.']], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);

        $a = $this->in(fn () => app(Assistent::class)->antwort('Was hat Nicole Beispiel gebucht?', $this->lea));
        $this->assertStringContainsString('4 von 5', $a['text']);
        $this->assertCount(1, $a['menschen']);
        $this->assertSame('nicole@test.ch', $a['menschen'][0]['mail']);
        $this->assertSame('5 gesamt, 1 gehabt, 0 geplant, 4 offen', $a['menschen'][0]['sitzungen']);
        $this->assertStringContainsString('Sitzung 1', $a['menschen'][0]['letzter_1zu1_termin']);
        $this->assertStringContainsString('/coach/memberships/', $a['menschen'][0]['dossier']);
        Http::assertSent(fn ($req) => str_contains($req->body(), 'Andrea macht die Rechnungen') && str_contains($req->body(), 'nicole@test.ch') && str_contains($req->body(), 'SO IST DIE APP AUFGEBAUT'));

        // Nur der Vorname: zwei Nicoles, also mehrdeutig
        $a = $this->in(fn () => app(Assistent::class)->antwort('Was hat Nicole gebucht?', $this->lea));
        $this->assertSame([], $a['menschen']);
        $this->assertArrayHasKey('Nicole', $a['mehrdeutig']);
        $this->assertCount(2, $a['mehrdeutig']['Nicole']);
    }

    public function test_wo_finde_ich_liefert_orte_auch_ohne_ki(): void
    {
        config(['ai.anthropic_key' => null]);
        $a = $this->in(fn () => app(Assistent::class)->antwort('Wo gebe ich eine Aufzeichnung frei?', $this->lea));
        $this->assertSame('', $a['text']);
        $this->assertNotEmpty($a['fehler']);
        $this->assertSame('Termine und Aufzeichnungen', $a['orte'][0]['t']);
    }

    public function test_seite_mit_fragen_und_themen_pruefen(): void
    {
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Im Coach-Bereich unter Personen.']], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);
        $this->in(function () {
            Filament::setCurrentPanel(Filament::getPanel('coach'));
            $this->actingAs($this->lea);
            Livewire::test(AssistentSeite::class)
                ->assertSee('Frag mich')
                ->set('frage', 'Wo sehe ich, wer wartet?')->call('fragen')
                ->assertSee('Im Coach-Bereich unter Personen')->assertSee('Personen und Dossiers')
                ->call('reiterWaehlen', 'themen')->assertSee('Die Schneekugel')->assertSee('Hilft, wenn es stürmt')
                ->call('passt', FinderProfile::first()->id)->assertSee('Hier ist alles geprüft');
        });
        $this->assertTrue($this->in(fn () => FinderProfile::first()->is_checked));
        $this->actingAs($this->lea)->get('http://a.test/coach/assistent')->assertOk();
        $this->actingAs($this->nicole)->get('http://a.test/coach/assistent')->assertForbidden();
    }
}

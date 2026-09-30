<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Kleinigkeiten der Oberflaeche: "Gehoert zu" ohne Arbeitsbuch und ohne Namen im 1:1, Sichtbarkeit mit Namen der Coachin, Logo aus dem Speicher. */
class OberflaecheTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
        $this->in(function () {
            foreach ([['hybrid', 'Hybrid-Coaching', 'hybrid'], ['idee', 'Von der Idee zur zahlenden Kundin', 'workbook'], ['eins', '1:1 Begleitung Anna Muster', 'one_on_one']] as [$slug, $titel, $typ]) {
                $p = Program::create(['slug' => $slug, 'title' => $titel, 'type' => $typ, 'is_published' => true]);
                ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            }
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_gehoert_zu_und_sichtbarkeit(): void
    {
        $r = $this->actingAs($this->anna)->get('http://a.test/aufgaben')->assertOk();
        $r->assertSee('Gehört zu')->assertDontSee('>Kurs<', false)
            ->assertSee('Hybrid-Coaching')->assertSee('1:1 Coaching')->assertDontSee('1:1 Begleitung Anna')
            ->assertDontSee('Von der Idee zur zahlenden Kundin')
            ->assertSee('>Lea<', false)->assertDontSee('Meine Coachin');
        // Menue: das Arbeitsbuch ist kein eigener Kurs
        $r->assertSee('Meine Kurse')->assertDontSee('zahlenden Kundin');
        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()->assertSee('Gehört zu')->assertSee('>Lea<', false);
    }

    public function test_logo_aus_dem_speicher(): void
    {
        Storage::fake('local');
        Storage::put('tenants/'.$this->a->id.'/branding/logo-1.png', 'PNGDATEN');
        $this->get('http://a.test/branding/logo-1.png')->assertOk()->assertHeader('Cache-Control', 'max-age=604800, public');
        $this->get('http://a.test/branding/gibt-es-nicht.png')->assertNotFound();
        $this->get('http://a.test/branding/..%2F..%2F.env')->assertNotFound();
    }
}

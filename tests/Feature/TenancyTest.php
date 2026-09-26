<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\Concerns\BelongsToTenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_ergibt_mandant(): void
    {
        $t = Tenant::create(['slug' => 'a', 'name' => 'A', 'branding' => ['app_name' => 'Coaching A']]);
        $t->domains()->create(['domain' => 'a.test', 'is_primary' => true]);

        $this->get('http://a.test/anmelden')->assertOk()->assertSee('Coaching A');
    }

    public function test_unbekannte_domain_gibt_404(): void
    {
        config(['tenancy.fallback_slug' => null]);
        $this->get('http://unbekannt.test/anmelden')->assertNotFound();
    }

    public function test_branding_landet_als_css_variablen_und_manifest(): void
    {
        $t = Tenant::create(['slug' => 'a', 'name' => 'A', 'branding' => ['app_name' => 'Coaching A', 'primary' => '#123456', 'short_name' => 'CA']]);
        $t->domains()->create(['domain' => 'a.test', 'is_primary' => true]);

        $this->get('http://a.test/anmelden')->assertOk()->assertSee('--c-primary:#123456', false);
        $this->get('http://a.test/manifest.webmanifest')->assertOk()
            ->assertJsonPath('name', 'Coaching A')->assertJsonPath('short_name', 'CA');
    }

    public function test_daten_sind_getrennt(): void
    {
        Schema::create('probe', function ($t) {
            $t->id();
            $t->foreignId('tenant_id');
            $t->string('x');
            $t->timestamps();
        });
        $model = new class extends Model
        {
            use BelongsToTenant;

            protected $table = 'probe';

            protected $guarded = [];
        };

        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $cur = app(CurrentTenant::class);

        $cur->run($a, fn () => $model::create(['x' => 'von a']));
        $cur->run($b, fn () => $model::create(['x' => 'von b']));

        $this->assertSame(['von a'], $cur->run($a, fn () => $model::pluck('x')->all()));
        $this->assertSame(['von b'], $cur->run($b, fn () => $model::pluck('x')->all()));
        $this->assertSame([], $model::pluck('x')->all()); // ohne Mandant: nichts
    }
}

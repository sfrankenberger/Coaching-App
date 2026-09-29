<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Shop\Bexio;
use App\Shop\Buchhaltung;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Buchhaltung je Mandant (bexio): Rechnungen im Profil und im Dossier, PDF, OAuth-Verbindung, Mandantentrennung. */
class BuchhaltungTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected User $mia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['buchhaltung' => ['anbieter' => 'bexio', 'bexio' => ['token' => 'fest-123']]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
        $this->b->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
        $this->mia = User::factory()->create(['name' => 'Mia Muster', 'email' => 'mia@example.com']);
        $this->a->users()->attach($this->mia, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
    }

    protected function bexioFake(): void
    {
        Http::fake([
            'api.bexio.com/2.0/contact/search*' => function ($request) {
                $mail = $request->data()[0]['value'] ?? '';

                return Http::response($mail === 'anna@example.com' ? [['id' => 77, 'mail' => $mail]] : []);
            },
            'api.bexio.com/2.0/kb_invoice/search*' => function ($request) {
                $kid = (int) ($request->data()[0]['value'] ?? 0);

                return Http::response($kid === 77 ? [
                    ['id' => 501, 'document_nr' => 'RE-2026-0501', 'title' => 'Hybrid-Coaching', 'is_valid_from' => '2026-09-01', 'is_valid_to' => '2026-09-30', 'total' => 1200.0, 'currency_id' => 1, 'kb_item_status_id' => 8, 'network_link' => 'https://office.bexio.com/pay/501'],
                    ['id' => 502, 'document_nr' => 'RE-2026-0502', 'title' => 'Minikurs', 'is_valid_from' => '2026-08-01', 'is_valid_to' => '2026-08-31', 'total' => 90.0, 'currency_id' => 1, 'kb_item_status_id' => 9, 'network_link' => ''],
                    ['id' => 503, 'document_nr' => '', 'title' => 'Entwurf', 'is_valid_from' => '2026-09-20', 'total' => 1.0, 'currency_id' => 1, 'kb_item_status_id' => 7],
                ] : []);
            },
            'api.bexio.com/2.0/kb_invoice/501/pdf' => Http::response(['content' => base64_encode("%PDF-1.4\n%test")]),
            'api.bexio.com/2.0/kb_invoice/*/pdf' => Http::response(['message' => 'not found'], 404),
        ]);
    }

    public function test_rechnungen_im_profil_mit_pdf_und_bezahlen_link(): void
    {
        $this->bexioFake();
        $r = $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk();
        $r->assertSee('Deine Rechnungen')->assertSee('RE-2026-0501')->assertSee("1'200.00 CHF")->assertSee('Online bezahlen')->assertSee('office.bexio.com/pay/501')
            ->assertSee('RE-2026-0502')->assertSee('bezahlt')->assertDontSee('Entwurf');

        // Kontakt-ID an der Mitgliedschaft gemerkt, kein zweites Suchen
        $this->assertSame(77, app(CurrentTenant::class)->run($this->a, fn () => Membership::where('user_id', $this->anna->id)->first()->setting('buchhaltung.kontakt_id')));

        $this->actingAs($this->anna)->get('http://a.test/rechnungen/501/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        // Fremde Rechnung: Mia hat keinen bexio-Kontakt, 501 gehoert Anna
        $this->actingAs($this->mia)->get('http://a.test/rechnungen/501/pdf')->assertNotFound();
        $this->actingAs($this->mia)->get('http://a.test/profil')->assertOk()->assertDontSee('Deine Rechnungen');
    }

    public function test_dossier_zeigt_rechnungen_fuer_das_team(): void
    {
        $this->bexioFake();
        $m = app(CurrentTenant::class)->run($this->a, fn () => Membership::where('user_id', $this->anna->id)->first());
        $r = $this->actingAs($this->lea)->get("http://a.test/coachees/{$m->id}?r=rechnungen")->assertOk();
        $r->assertSee('RE-2026-0501')->assertSee('2 Rechnungen in bexio')->assertSee("offen: 1'200.00 CHF");
        $this->actingAs($this->lea)->get("http://a.test/coachees/{$m->id}/rechnung/501")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->lea)->get("http://a.test/coachees/{$m->id}/rechnung/999")->assertNotFound();
        $this->actingAs($this->anna)->get("http://a.test/coachees/{$m->id}/rechnung/501")->assertForbidden();
    }

    public function test_mandant_ohne_buchhaltung_sieht_nichts_und_ruft_bexio_nicht(): void
    {
        $this->bexioFake();
        $this->actingAs($this->anna)->get('http://b.test/profil')->assertOk()->assertDontSee('Deine Rechnungen');
        Http::assertNothingSent();
        $this->assertNull(Buchhaltung::fuer($this->b));
    }

    public function test_stoerung_bei_bexio_bricht_das_profil_nicht(): void
    {
        Http::fake(['api.bexio.com/*' => Http::response(['message' => 'kaputt'], 500)]);
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertDontSee('Deine Rechnungen');
    }

    public function test_oauth_verbindung_und_erneuern_bei_401(): void
    {
        $this->a->forceFill(['settings' => ['buchhaltung' => ['anbieter' => 'bexio', 'bexio' => ['client_id' => 'cid', 'client_secret' => 'geheim']]]])->save();
        Http::fake([
            'auth.bexio.com/*/token' => Http::sequence()
                ->push(['access_token' => 'acc-1', 'refresh_token' => 'ref-1', 'expires_in' => 3600])
                ->push(['access_token' => 'acc-2', 'refresh_token' => 'ref-2', 'expires_in' => 3600]),
            'api.bexio.com/2.0/company_profile' => Http::response([['name' => 'Lea Wernli Coaching']]),
            'api.bexio.com/2.0/contact/search*' => Http::sequence()->push(['message' => 'abgelaufen'], 401)->push([['id' => 77]]),
            'api.bexio.com/2.0/kb_invoice/search*' => Http::response([]),
        ]);

        // Start: nur die Inhaberin, mit State zu bexio
        $this->actingAs($this->anna)->get('http://a.test/buchhaltung/bexio/start')->assertForbidden();
        $r = $this->actingAs($this->lea)->get('http://a.test/buchhaltung/bexio/start')->assertRedirect();
        $ziel = $r->headers->get('Location');
        $this->assertStringStartsWith(Bexio::AUTH.'/auth?', $ziel);
        parse_str(parse_url($ziel, PHP_URL_QUERY), $q);
        $this->assertSame('cid', $q['client_id']);
        $this->assertSame('http://a.test/buchhaltung/bexio/rueckkehr', $q['redirect_uri']);

        // Rueckkehr mit falschem State wird abgewiesen, mit richtigem gespeichert
        $this->actingAs($this->lea)->withSession(['bexio_state' => $q['state']])->get('http://a.test/buchhaltung/bexio/rueckkehr?state=falsch&code=abc')->assertRedirect('/coach/buchhaltung')->assertSessionHas('fehler');
        $this->actingAs($this->lea)->withSession(['bexio_state' => $q['state']])->get('http://a.test/buchhaltung/bexio/rueckkehr?state='.$q['state'].'&code=abc')->assertRedirect('/coach/buchhaltung')->assertSessionHas('meldung');
        $e = $this->a->fresh()->setting('buchhaltung.bexio');
        $this->assertSame('ref-1', $e['refresh_token']);
        $this->assertSame('acc-1', $e['access_token']);
        $this->assertSame('Lea Wernli Coaching', $e['firma']);

        // Ein 401 fuehrt zu einmaligem Erneuern (rotiertes Refresh-Token wird gemerkt) und Wiederholung
        $bexio = new Bexio($this->a->fresh());
        $this->assertSame(77, app(CurrentTenant::class)->run($this->a, fn () => $bexio->kontaktId($this->anna)));
        $this->assertSame('ref-2', $this->a->fresh()->setting('buchhaltung.bexio.refresh_token'));
        Http::assertSentCount(5);
    }

    public function test_buchhaltungsseite_nur_fuer_inhaberin(): void
    {
        $this->actingAs($this->lea)->get('http://a.test/coach/buchhaltung')->assertOk()->assertSee('Buchhaltung')->assertSee('fester Token');
        $team = User::factory()->create(['name' => 'Team']);
        $this->a->users()->attach($team, ['role' => Role::Team->value, 'status' => 'active']);
        $this->actingAs($team)->get('http://a.test/coach/buchhaltung')->assertForbidden();
    }
}

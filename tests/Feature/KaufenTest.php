<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\RechnungMail;
use App\Models\Entitlement;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verkauf;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Angebote oeffentlich: Schnittstelle fuer die Website, Kauflink mit Kauf auf Rechnung, Angebote in der App. */
class KaufenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'currency' => 'CHF', 'settings' => ['coach_name' => 'Lea', 'buchhaltung' => ['anbieter' => 'bexio', 'bexio' => [
            'token' => 'fest-123',
            'stammdaten' => ['waehrungen' => [1 => 'CHF', 2 => 'EUR']],
            'schreiben' => ['user_id' => 3, 'account_id' => 90, 'frist' => 30],
        ]]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->offer = app(CurrentTenant::class)->run($this->a, function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid', 'is_published' => true]);
            $o = Offer::create(['title' => 'Hybrid-Coaching Herbst', 'type' => 'hybrid', 'access_days' => 180, 'is_active' => true, 'settings' => ['sichtbar' => true, 'preis_chf' => 1200, 'preis_eur' => 1250, 'aktion_preis_chf' => 990, 'aktion_bis' => now()->addDays(3)->toDateString(), 'teaser' => 'Vom Kopf und Herz in die Welt.', 'bild_url' => 'https://example.org/bild.jpg']]);
            $o->programs()->attach($p, ['tenant_id' => $this->a->id]);
            Offer::create(['title' => 'Nur über Link', 'access_days' => 30, 'is_active' => true, 'settings' => ['preis_chf' => 50]]);
            Offer::create(['title' => 'Ohne Preis', 'is_active' => true, 'settings' => ['sichtbar' => true]]);

            return $o;
        });
        app(CurrentTenant::class)->run($this->b, fn () => Offer::create(['title' => 'Fremdes Angebot', 'is_active' => true, 'settings' => ['sichtbar' => true, 'preis_chf' => 10]]));
    }

    public function test_slug_entsteht_aus_dem_titel_und_ist_je_mandant_eindeutig(): void
    {
        $this->assertSame('hybrid-coaching-herbst', $this->offer->slug);
        $zweites = app(CurrentTenant::class)->run($this->a, fn () => Offer::create(['title' => 'Hybrid-Coaching Herbst']));
        $this->assertSame('hybrid-coaching-herbst-2', $zweites->slug);
        $fremd = app(CurrentTenant::class)->run($this->b, fn () => Offer::create(['title' => 'Hybrid-Coaching Herbst']));
        $this->assertSame('hybrid-coaching-herbst', $fremd->slug, 'gleicher Slug in einem anderen Mandanten ist erlaubt');
    }

    public function test_oeffentliche_schnittstelle_liefert_sichtbare_angebote_je_mandant(): void
    {
        $r = $this->getJson('http://a.test/api/angebote')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        $r->assertJsonCount(1, 'angebote')->assertJsonPath('angebote.0.slug', 'hybrid-coaching-herbst')->assertJsonPath('angebote.0.preise.CHF', 990)->assertJsonPath('angebote.0.preise.EUR', 1250)
            ->assertJsonPath('angebote.0.preise_regulaer.CHF', 1200)->assertJsonPath('angebote.0.kaufen', 'http://a.test/kaufen/hybrid-coaching-herbst')->assertJsonPath('angebote.0.programme.0', 'Hybrid-Coaching');
        $this->assertStringNotContainsString('Fremdes Angebot', $r->getContent());
        $this->assertStringNotContainsString('Ohne Preis', $r->getContent());
        // Nur ueber Link: nicht in der Liste, aber einzeln abrufbar
        $this->getJson('http://a.test/api/angebote/nur-uber-link')->assertOk()->assertJsonPath('titel', 'Nur über Link');
        $this->getJson('http://a.test/api/angebote/ohne-preis')->assertNotFound();
        $this->getJson('http://b.test/api/angebote/hybrid-coaching-herbst')->assertNotFound();
        $this->getJson('http://b.test/api/angebote')->assertOk()->assertJsonPath('angebote.0.titel', 'Fremdes Angebot');
    }

    public function test_gast_kauft_auf_rechnung_und_bekommt_konto_rechnung_und_zugang(): void
    {
        Mail::fake();
        Http::fake([
            'api.bexio.com/2.0/contact/search*' => Http::response([]),
            'api.bexio.com/2.0/contact' => Http::response(['id' => 88]),
            'api.bexio.com/2.0/kb_invoice/701/*' => Http::response(['success' => true]),
            'api.bexio.com/2.0/kb_invoice/701' => Http::response(['id' => 701, 'document_nr' => 'RE-0701', 'network_link' => 'https://office.bexio.com/pay/701', 'kb_item_status_id' => 8]),
            'api.bexio.com/2.0/kb_invoice' => Http::response(['id' => 701]),
        ]);
        $r = $this->get('http://a.test/kaufen/hybrid-coaching-herbst?ref=herbst-webinar')->assertOk();
        $r->assertSee('Hybrid-Coaching Herbst')->assertSee('990.00 CHF')->assertSee("1'200.00 CHF")->assertSee('Aktion')->assertSee('Auf Rechnung kaufen')->assertSee('Dein Name');
        $this->get('http://a.test/kaufen/hybrid-coaching-herbst?w=EUR')->assertOk()->assertSee("1'250.00 EUR");

        // Ohne Widerrufsverzicht und Rechnungsadresse geht es nicht
        $this->post('http://a.test/kaufen/hybrid-coaching-herbst', ['name' => 'Nora Neu', 'email' => 'Nora@Test.ch', 'waehrung' => 'CHF', 'zahlung' => 'rechnung', 'agb' => 1])->assertSessionHasErrors(['widerruf', 'strasse']);
        $this->post('http://a.test/kaufen/hybrid-coaching-herbst', ['name' => 'Nora Neu', 'email' => 'Nora@Test.ch', 'waehrung' => 'CHF', 'zahlung' => 'rechnung', 'ref' => 'herbst-webinar', 'agb' => 1, 'widerruf' => 1, 'strasse' => 'Weg 1', 'plz' => '8000', 'ort' => 'Zürich', 'land' => 'CH'])
            ->assertRedirect('http://a.test/kaufen/hybrid-coaching-herbst/danke');
        $nora = User::where('email', 'nora@test.ch')->first();
        $this->assertNotNull($nora);
        $this->assertSame('Nora Neu', $nora->name);
        app(CurrentTenant::class)->run($this->a, function () use ($nora) {
            $this->assertSame(Role::Member, Membership::where('user_id', $nora->id)->first()->role);
            $v = Verkauf::first();
            $this->assertSame(990.0, (float) $v->betrag, 'Aktionspreis');
            $this->assertSame('rechnung', $v->zahlungsart);
            $this->assertSame('kasse:herbst-webinar', $v->herkunft);
            $this->assertSame('RE-0701', $v->rechnung_nr);
            $this->assertNull($v->created_by);
            $this->assertNotNull($v->settings['widerruf_verzicht_at']);
            $this->assertSame('Zürich', $v->settings['adresse']['ort']);
            $this->assertSame('CHF', Membership::where('user_id', $nora->id)->first()->setting('waehrung'));
            $this->assertTrue(Entitlement::where('user_id', $nora->id)->first()->isCurrent());
        });
        Mail::assertSent(RechnungMail::class, fn ($m) => $m->hasTo('nora@test.ch'));
        $this->get('http://a.test/kaufen/hybrid-coaching-herbst/danke')->assertOk()->assertSee('Danke, Nora')->assertSee('Jetzt online bezahlen');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/2.0/kb_invoice') && $r['positions'][0]['unit_price'] === '990.00');
    }

    public function test_angemeldete_person_kauft_mit_einem_klick_und_sieht_angebote_in_der_app(): void
    {
        Mail::fake();
        Http::fake(['api.bexio.com/*' => Http::response(['message' => 'aus'], 500)]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);

        $this->actingAs($anna)->get('http://a.test/angebote')->assertOk()->assertSee('Hybrid-Coaching Herbst')->assertSee('Kaufen')->assertDontSee('Nur über Link')->assertDontSee('Fremdes Angebot');
        $this->actingAs($anna)->get('http://a.test/kaufen/hybrid-coaching-herbst')->assertOk()->assertSee('Du bist angemeldet als')->assertDontSee('Dein Name');
        $this->actingAs($anna)->post('http://a.test/kaufen/hybrid-coaching-herbst', ['zahlung' => 'rechnung', 'agb' => 1, 'widerruf' => 1, 'strasse' => 'Weg 1', 'plz' => '8000', 'ort' => 'Zürich'])->assertRedirect('http://a.test/kaufen/hybrid-coaching-herbst/danke');
        $this->assertTrue(app(CurrentTenant::class)->run($this->a, fn () => Entitlement::where('user_id', $anna->id)->first()->isCurrent()), 'Zugang auch wenn bexio ausfaellt');
        $this->actingAs($anna)->get('http://a.test/angebote')->assertOk()->assertSee('Hast du schon');
        $this->actingAs($anna)->get('http://a.test/kaufen/hybrid-coaching-herbst')->assertOk()->assertSee('Das hast du schon');
    }

    public function test_gratis_angebot_und_schutz(): void
    {
        Mail::fake();
        app(CurrentTenant::class)->run($this->a, fn () => Offer::create(['title' => 'Der Anfang', 'is_free' => true, 'is_active' => true, 'settings' => ['sichtbar' => true]]));
        $this->get('http://a.test/kaufen/der-anfang')->assertOk()->assertSee('Kostenlos dabei sein');
        // Honigtopf gefuellt: abgelehnt
        $this->post('http://a.test/kaufen/der-anfang', ['name' => 'Bot', 'email' => 'bot@test.ch', 'zahlung' => 'gratis', 'agb' => 1, 'website' => 'x'])->assertSessionHasErrors('website');
        // Ohne Einverstaendnis: abgelehnt
        $this->post('http://a.test/kaufen/der-anfang', ['name' => 'Mia', 'email' => 'mia@test.ch', 'zahlung' => 'gratis'])->assertSessionHasErrors('agb');
        $this->post('http://a.test/kaufen/der-anfang', ['name' => 'Mia', 'email' => 'mia@test.ch', 'zahlung' => 'gratis', 'agb' => 1])->assertRedirect();
        $v = app(CurrentTenant::class)->run($this->a, fn () => Verkauf::first());
        $this->assertSame('kostenlos', $v->zahlungsart);
        // Ohne Haekchen kein Newsletter-Tag, mit Haekchen Tag plus Einwilligung (Kontakt ist ueber den Zugang schon bestaetigt)
        $k = app(CurrentTenant::class)->run($this->a, fn () => \App\Models\Kontakt::where('email', 'mia@test.ch')->first());
        $this->assertNotNull($k);
        $this->assertNotContains('newsletter', $k->tags ?? []);
        $this->post('http://a.test/kaufen/der-anfang', ['name' => 'Nora', 'email' => 'nora@test.ch', 'zahlung' => 'gratis', 'agb' => 1, 'newsletter' => 1])->assertRedirect();
        $k2 = app(CurrentTenant::class)->run($this->a, fn () => \App\Models\Kontakt::where('email', 'nora@test.ch')->first());
        $this->assertContains('newsletter', $k2->tags);
        $this->assertSame('bestaetigt', $k2->status);
        $this->assertSame('kasse:der-anfang', $k2->einwilligung['newsletter']['herkunft']);
        Http::assertNothingSent();
        Mail::assertSent(RechnungMail::class);
        // Unbekannt und fremder Mandant
        $this->get('http://a.test/kaufen/gibt-es-nicht')->assertNotFound();
        $this->get('http://b.test/kaufen/der-anfang')->assertNotFound();
    }
}

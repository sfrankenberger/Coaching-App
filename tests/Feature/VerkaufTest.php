<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\RechnungMail;
use App\Models\Entitlement;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verkauf;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Verkaufen im Dossier mit Rechnung in bexio: Kontakt, Rechnung, Zahlung, Mail mit PDF, Zugang nach Zahlungseingang. */
class VerkaufTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected Offer $offer;

    protected Membership $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'currency' => 'CHF', 'settings' => ['coach_name' => 'Lea', 'buchhaltung' => ['anbieter' => 'bexio', 'bexio' => [
            'token' => 'fest-123',
            'stammdaten' => ['waehrungen' => [1 => 'CHF', 2 => 'EUR'], 'konten' => [90 => '3400 Dienstleistungen'], 'bank' => [5 => 'Konto CH00'], 'steuern' => []],
            'schreiben' => ['user_id' => 3, 'account_id' => 90, 'bank_account_id' => 5, 'zahlung_bank_account_id' => 5, 'frist' => 30, 'kopie_mail' => 'ablage@example.com'],
        ]]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);
        $this->a->users()->attach($this->anna, ['role' => Role::Guest->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
        $this->b->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        [$this->offer, $this->m] = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid-Coaching', 'type' => 'hybrid', 'is_published' => true]);
            $offer = Offer::create(['title' => 'Hybrid-Coaching Herbst', 'access_days' => 180, 'is_active' => true]);
            $offer->programs()->attach($p, ['tenant_id' => $this->a->id]);

            return [$offer, Membership::where('user_id', $this->anna->id)->first()];
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected int $rechnungStatus = 8;

    /** bexio-Antworten: Kontakt fehlt und wird angelegt, Rechnung 701 entsteht, Details, PDF. */
    protected function bexioFake(int $statusNachher = 8): void
    {
        $this->rechnungStatus = $statusNachher;
        Http::preventStrayRequests();
        Http::fake([
            'api.bexio.com/2.0/contact/search*' => Http::response([]),
            'api.bexio.com/2.0/contact' => Http::response(['id' => 88]),
            'api.bexio.com/2.0/kb_invoice/701/issue' => Http::response(['success' => true]),
            'api.bexio.com/2.0/kb_invoice/701/payment' => Http::response(['id' => 1]),
            'api.bexio.com/2.0/kb_invoice/701/send' => Http::response(['success' => true]),
            'api.bexio.com/2.0/kb_invoice/701/pdf' => Http::response(['content' => base64_encode("%PDF-1.4\n%r")]),
            'api.bexio.com/2.0/kb_invoice/701' => fn () => Http::response(['id' => 701, 'document_nr' => 'RE-0701', 'network_link' => 'https://office.bexio.com/pay/701', 'kb_item_status_id' => $this->rechnungStatus]),
            'api.bexio.com/2.0/kb_invoice' => Http::response(['id' => 701]),
        ]);
    }

    public function test_verkauf_auf_rechnung_legt_kontakt_und_rechnung_an_und_mailt_pdf(): void
    {
        Mail::fake();
        $this->bexioFake();
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 1200, 'waehrung' => 'CHF', 'zahlungsart' => 'rechnung', 'rechnung' => 1, 'mail' => 1])
            ->assertRedirect("http://a.test/coachees/{$this->m->id}?r=rechnungen")->assertSessionHas('meldung');

        $v = $this->in(fn () => Verkauf::first());
        $this->assertSame('offen', $v->status);
        $this->assertSame('701', $v->rechnung_id);
        $this->assertSame('RE-0701', $v->rechnung_nr);
        $this->assertSame('https://office.bexio.com/pay/701', $v->rechnung_link);
        $this->assertSame(now()->addDays(30)->toDateString(), $v->faellig_am->toDateString());
        $this->assertSame(88, $this->in(fn () => $this->m->fresh()->setting('buchhaltung.kontakt_id')));
        $this->assertSame('member', $this->in(fn () => $this->m->fresh()->role->value), 'Gast wird Mitglied');
        $this->assertTrue($this->in(fn () => Entitlement::where('user_id', $this->anna->id)->first()->isCurrent()), 'Zugang sofort (Vorgabe)');

        // Rechnung in bexio: Titel, Betrag brutto, Konto, Faelligkeit, Referenz
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/2.0/kb_invoice') && $r['title'] === 'Hybrid-Coaching Herbst' && $r['positions'][0]['unit_price'] === '1200.00' && $r['positions'][0]['account_id'] === 90 && $r['currency_id'] === 1 && $r['api_reference'] === 'app-verkauf-'.$v->id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/2.0/contact') && $r['name_1'] === 'Muster' && $r['name_2'] === 'Anna' && $r['mail'] === 'anna@example.com');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/701/payment'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/701/send') && $r['recipient_email'] === 'ablage@example.com');

        Mail::assertSent(RechnungMail::class, fn (RechnungMail $m) => $m->hasTo('anna@example.com') && count($m->attachments()) === 1 && str_contains($m->envelope()->subject, 'Deine Rechnung'));

        // Dossier zeigt den Verkauf am Paket, Rechnungen-Reiter hat sie (frischer Cache)
        $r = $this->actingAs($this->lea)->get("http://a.test/coachees/{$this->m->id}")->assertOk();
        $r->assertSee("1'200.00 CHF")->assertSee('Rechnung RE-0701')->assertSee('auf Rechnung');
    }

    public function test_bereits_bezahlt_bucht_zahlung_und_quittung(): void
    {
        Mail::fake();
        $this->bexioFake(9);
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 90.5, 'waehrung' => 'EUR', 'zahlungsart' => 'bezahlt', 'rechnung' => 1, 'mail' => 1])->assertRedirect();
        $v = $this->in(fn () => Verkauf::first());
        $this->assertSame('bezahlt', $v->status);
        $this->assertNotNull($v->bezahlt_am);
        $this->assertNull($v->faellig_am);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/701/payment') && $r['value'] === '90.50' && $r['bank_account_id'] === 5);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/2.0/kb_invoice') && $r['currency_id'] === 2 && $r['is_valid_to'] === now()->toDateString());
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/701/send'));
        Mail::assertSent(RechnungMail::class, fn (RechnungMail $m) => str_contains($m->envelope()->subject, 'Deine Quittung'));
    }

    public function test_zugang_erst_nach_zahlungseingang_und_abgleich_schaltet_frei(): void
    {
        Mail::fake();
        $s = $this->a->settings;
        $s['buchhaltung']['zugang_bei_rechnung'] = 'bezahlt';
        $this->a->forceFill(['settings' => $s])->save();
        $this->bexioFake(8);
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 1200, 'zahlungsart' => 'rechnung', 'rechnung' => 1, 'mail' => 1])
            ->assertSessionHas('meldung', fn ($t) => str_contains($t, 'Zugang ab Zahlungseingang'));
        $e = $this->in(fn () => Entitlement::where('user_id', $this->anna->id)->first());
        $this->assertSame('pending', $e->status);
        $this->assertFalse($e->isCurrent());
        // Anna sieht den Kurs noch nicht
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid')->assertForbidden();

        // Noch offen: nichts passiert
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->assertSuccessful();
        $this->assertSame('offen', $this->in(fn () => Verkauf::first()->status));

        // bexio meldet bezahlt: Verkauf bezahlt, Zugang aktiv, Bescheid
        $this->rechnungStatus = 9;
        $this->artisan('buchhaltung:zahlungen', ['tenant' => 'a'])->expectsOutputToContain('bezahlt')->assertSuccessful();
        $this->assertSame('bezahlt', $this->in(fn () => Verkauf::first()->status));
        $this->assertTrue($this->in(fn () => Entitlement::where('user_id', $this->anna->id)->first()->isCurrent()));
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid')->assertOk();
    }

    public function test_zugang_laeuft_ab_und_nimmt_den_kurs_wieder_weg(): void
    {
        Mail::fake();
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 0, 'tage' => 30])->assertRedirect();
        $access = app(ProgramAccess::class);
        $p = $this->in(fn () => Program::where('slug', 'hybrid')->first());
        $this->assertTrue($this->in(fn () => $access->canView($this->anna, $p)));
        $this->assertNotNull($this->in(fn () => ProgramMember::where('user_id', $this->anna->id)->first()->entitlement_id));

        $this->travel(31)->days();
        $this->assertFalse($this->in(fn () => $access->canView($this->anna, $p)), 'Nach Ablauf des Zugangs ist der Kurs zu');

        // Ein neuer Verkauf haengt die Mitgliedschaft an den neuen Zugang
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 0, 'tage' => 10])->assertRedirect();
        $this->assertTrue($this->in(fn () => $access->canView($this->anna, $p)));
        $this->assertSame(1, $this->in(fn () => ProgramMember::where('user_id', $this->anna->id)->count()));
        $this->travelBack();
    }

    public function test_kostenlos_und_ohne_rechnung(): void
    {
        Mail::fake();
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 0, 'rechnung' => 1])->assertRedirect("http://a.test/coachees/{$this->m->id}?r=kurs");
        $v = $this->in(fn () => Verkauf::first());
        $this->assertSame('kostenlos', $v->zahlungsart);
        $this->assertSame('bezahlt', $v->status);
        $this->assertNull($v->rechnung_id);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_stoerung_bei_bexio_gibt_zugang_trotzdem_und_meldet(): void
    {
        Mail::fake();
        Http::fake(['api.bexio.com/*' => Http::response(['message' => 'kaputt'], 500)]);
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 500, 'zahlungsart' => 'rechnung', 'rechnung' => 1])
            ->assertRedirect("http://a.test/coachees/{$this->m->id}?r=rechnungen")->assertSessionHas('fehler', fn ($t) => str_contains($t, 'Rechnung konnte nicht angelegt werden'));
        $v = $this->in(fn () => Verkauf::first());
        $this->assertNull($v->rechnung_id);
        $this->assertStringContainsString('bexio 500', $v->settings['rechnung_fehler']);
        $this->assertTrue($this->in(fn () => Entitlement::where('user_id', $this->anna->id)->first()->isCurrent()));
    }

    public function test_ein_1zu1_angebot_legt_die_begleitung_je_person_an(): void
    {
        Mail::fake();
        $offer = $this->in(fn () => Offer::create(['title' => '1:1 Coaching', 'type' => 'one_on_one', 'is_active' => true, 'settings' => ['sitzungen' => 4]]));
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $offer->id, 'betrag' => 0])->assertRedirect();
        $p = $this->in(fn () => Program::where('type', 'one_on_one')->first());
        $this->assertSame('1:1 Begleitung Anna Muster', $p->title);
        $this->assertSame(4, $p->settings['sitzungen_gesamt']);
        $this->assertTrue($this->in(fn () => $p->members()->where('user_id', $this->anna->id)->exists()));
        // Zweiter Kauf: dieselbe Begleitung, Sitzungen kommen dazu
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $offer->id, 'betrag' => 0, 'sitzungen' => 2])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Program::where('type', 'one_on_one')->count()));
        $this->assertSame(2, $this->in(fn () => $p->members()->where('user_id', $this->anna->id)->first()->settings['sitzungen_extra']));
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertSee('6 von 6 offen');
    }

    public function test_mandant_b_sieht_verkaeufe_von_a_nicht(): void
    {
        Mail::fake();
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$this->m->id}/zugang", ['offer_id' => $this->offer->id, 'betrag' => 0])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Verkauf::count()));
        $this->assertSame(0, app(CurrentTenant::class)->run($this->b, fn () => Verkauf::count()));
    }
}

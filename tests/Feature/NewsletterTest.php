<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Pages\Einstellungen;
use App\Filament\Coach\Resources\Newsletter\Pages\EditNewsletter;
use App\Filament\Coach\Resources\Posts\Pages\EditPost;
use App\Filament\Coach\Resources\Serien\Pages\EditSerie;
use App\Mail\AbmeldeLinkMail;
use App\Mail\KontaktBestaetigenMail;
use App\Mail\NewsletterMail;
use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\NewsletterVersand;
use App\Models\Offer;
use App\Models\Post;
use App\Models\Serie;
use App\Models\SerienLauf;
use App\Models\Tenant;
use App\Models\User;
use App\Newsletter\Bausteine;
use App\Newsletter\Kontakte;
use App\Newsletter\Serien;
use App\Newsletter\Versand;
use App\Shop\Zugang;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use App\Models\Program;
use Tests\TestCase;

/** Etappe 11: Kontakte mit Double-Opt-in, Tags, Newsletter in Wellen mit Zaehlung, Serien, Abmeldung, Isolation. */
class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea', 'website' => 'https://lea.test']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach', 'email' => 'lea@example.com']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
    }

    protected function in(callable $fn, ?Tenant $t = null): mixed
    {
        return app(CurrentTenant::class)->run($t ?? $this->a, $fn);
    }

    public function test_anmeldung_mit_bestaetigung_tags_und_serie(): void
    {
        $this->in(fn () => Serie::create(['titel' => 'Freebie', 'tag' => 'freebie', 'aktiv' => true, 'schritte' => [
            ['tage' => 0, 'betreff' => 'Hier ist dein Freebie, {vorname}', 'text' => 'Viel Freude damit: [Download](https://lea.test/freebie.pdf)'],
            ['tage' => 2, 'betreff' => 'Und, wie war es?', 'text' => 'Schreib mir gern.'],
        ]]));

        // Formular von der Website: zurueck nur auf die eigene Website
        $this->post('http://a.test/newsletter/anmelden', ['email' => 'Nora@Test.ch', 'name' => 'Nora Neu', 'tag' => 'freebie, newsletter', 'einwilligung' => 1, 'zurueck' => 'https://lea.test/freebie/', 'herkunft' => 'website:freebie'])
            ->assertRedirect('https://lea.test/freebie/?newsletter=postfach');
        $this->post('http://a.test/newsletter/anmelden', ['email' => 'zwei@test.ch', 'einwilligung' => 1, 'zurueck' => 'https://boese.test/'])->assertOk()->assertSee('Schau in dein Postfach');
        $this->post('http://a.test/newsletter/anmelden', ['email' => 'drei@test.ch'])->assertSessionHasErrors('einwilligung');

        $k = $this->in(fn () => Kontakt::where('email', 'nora@test.ch')->first());
        $this->assertSame('angemeldet', $k->status);
        $this->assertSame('Nora Neu', $k->name);
        $this->assertSame([], $k->tags ?? []);
        $this->assertSame(['freebie', 'newsletter'], $k->settings['tags_offen']);
        $this->assertSame('website:freebie', $k->einwilligung['herkunft']);
        $this->assertNotEmpty($k->einwilligung['zeit']);
        $url = null;
        Mail::assertSent(KontaktBestaetigenMail::class, function (KontaktBestaetigenMail $m) use (&$url) {
            if ($m->hasTo('nora@test.ch')) {
                $url = $m->url;

                return true;
            }

            return false;
        });
        Mail::assertNotSent(NewsletterMail::class);

        // Bestaetigen: Tags gesetzt, Serie startet, Schritt 0 sofort
        $this->get($url)->assertOk()->assertSee('Du bist dabei, Nora');
        $k = $k->fresh();
        $this->assertSame('bestaetigt', $k->status);
        $this->assertSame(['freebie', 'newsletter'], $k->tags);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->hasTo('nora@test.ch') && $m->envelope()->subject === 'Hier ist dein Freebie, Nora');
        $lauf = $this->in(fn () => SerienLauf::first());
        $this->assertSame(1, $lauf->schritt);
        $this->assertNull($lauf->fertig_at);

        // Kaputte Signatur
        $this->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();

        // Zweiter Schritt nach zwei Tagen
        $this->in(fn () => app(Serien::class)->lauf());
        Mail::assertSentTimes(NewsletterMail::class, 1);
        $this->travel(2)->days();
        $this->assertSame(1, $this->in(fn () => app(Serien::class)->lauf()));
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->envelope()->subject === 'Und, wie war es?');
        $this->assertNotNull($this->in(fn () => SerienLauf::first()->fertig_at));

        // Abmelden mit einem Klick, danach keine Serienmails mehr und wieder anmelden
        $this->get('http://a.test/n/abmelden/'.$k->token)->assertOk()->assertSee('abgemeldet');
        $this->assertSame('abgemeldet', $k->fresh()->status);
        $this->post('http://a.test/n/dabei/'.$k->token)->assertOk()->assertSee('Du bist dabei');
        $this->assertSame('bestaetigt', $k->fresh()->status);

        // JSON-Anmeldung ueber die API, sofort (Veranstaltung)
        $this->postJson('http://a.test/api/anmelden', ['email' => 'live@test.ch', 'name' => 'Live', 'tag' => 'live-abend', 'einwilligung' => true, 'sofort' => true])->assertOk()->assertJson(['ok' => true, 'stand' => 'dabei']);
        $this->assertSame('bestaetigt', $this->in(fn () => Kontakt::where('email', 'live@test.ch')->first()->status));
    }

    public function test_serie_mit_bedingungen_anmeldelink_und_tags_nach_art(): void
    {
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@test.ch']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $kurs = $this->in(fn () => Program::create(['title' => 'Der Anfang', 'slug' => 'der-anfang', 'is_published' => true]));
        $offer = $this->in(fn () => Offer::create(['title' => 'Der Anfang', 'slug' => 'der-anfang', 'type' => 'free', 'is_free' => true, 'is_active' => true]));
        $this->in(fn () => $offer->programs()->attach($kurs, ['tenant_id' => $this->a->id]));
        $this->in(fn () => Serie::create(['titel' => 'Gratiskurs', 'tag' => 'gratiskurs', 'aktiv' => true, 'settings' => ['program_id' => $kurs->id], 'schritte' => [
            ['tage' => 0, 'betreff' => 'Dein Einstieg', 'text' => 'Hier ist dein Link: {anmeldelink}'],
            ['tage' => 1, 'betreff' => 'Noch nicht drin?', 'text' => 'Komm rein.', 'bedingung' => 'nicht_angemeldet'],
            ['tage' => 1, 'betreff' => 'Fertig!', 'text' => 'Gratuliere.', 'bedingung' => 'kurs_fertig'],
            ['tage' => 1, 'betreff' => 'Reden?', 'text' => 'Klarheitsgespräch.', 'bedingung' => 'kein_gespraech'],
        ]]));

        // Zugang zum Gratiskurs: Kontakt bekommt Angebots-Tag und "gratiskurs", die Serie startet mit frischem Link
        $this->in(fn () => app(\App\Shop\Zugang::class)->grant($this->anna, $offer, 'manual', 'test', now(), null, false));
        $k = $this->in(fn () => Kontakt::where('email', $this->anna->email)->first());
        $this->assertContains('gratiskurs', $k->tags);
        $this->assertContains('der-anfang', $k->tags);
        $this->assertSame($this->anna->id, $k->user_id);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->newsletter->betreff === 'Dein Einstieg' && str_contains($m->newsletter->text, 'http://a.test/') && ! str_contains($m->newsletter->text, '{anmeldelink}'));

        // Anna war schon in der App: Schritt 2 wird uebersprungen, Kurs nicht fertig: Schritt 3 auch, kein Gespraech: Schritt 4 kommt
        $this->in(fn () => $this->anna->membershipIn()->forceFill(['last_seen_at' => now()])->save());
        $this->travel(1)->days();
        $this->in(fn () => app(Serien::class)->lauf());
        Mail::assertNotSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->newsletter->betreff === 'Noch nicht drin?');
        $lauf = $this->in(fn () => SerienLauf::where('kontakt_id', $k->id)->first());
        $this->assertSame(2, $lauf->schritt, 'uebersprungen, naechster Schritt geplant');
        $this->travel(1)->days();
        $this->in(fn () => app(Serien::class)->lauf());
        Mail::assertNotSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->newsletter->betreff === 'Fertig!');
        $this->travel(1)->days();
        $this->in(fn () => app(Serien::class)->lauf());
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->newsletter->betreff === 'Reden?');
        $this->assertNotNull($lauf->fresh()->fertig_at);

        // 1:1-Angebot: Tag "1-1-coaching"
        $einzel = $this->in(fn () => Offer::create(['title' => '1:1', 'slug' => 'einzel', 'type' => 'one_on_one', 'is_active' => true]));
        $this->in(fn () => app(\App\Shop\Zugang::class)->grant($this->anna, $einzel, 'manual', 'x', now(), null, false));
        $this->assertContains('1-1-coaching', $k->fresh()->tags);
        $this->travelBack();
    }

    public function test_newsletter_in_wellen_mit_zaehlung_und_webversion(): void
    {
        $kontakte = app(Kontakte::class);
        [$anna, $bea, $carla, $dora] = $this->in(fn () => [
            $kontakte->anmelden('anna@test.ch', 'Anna Muster', ['newsletter'], [], false),
            $kontakte->anmelden('bea@test.ch', 'Bea', ['newsletter', 'club'], [], false),
            $kontakte->anmelden('carla@test.ch', 'Carla', ['club'], [], false),
            $kontakte->anmelden('dora@test.ch', 'Dora', ['newsletter'], [], true),   // wartet noch
        ]);
        $this->in(fn () => $kontakte->abmelden($carla));
        Mail::fake();

        $n = $this->in(fn () => Newsletter::create(['betreff' => 'Hallo {vorname}', 'vorschautext' => 'Kurz vorab', 'titel' => 'Neues im Herbst', 'text' => "Liebe {vorname}\n\nEs gibt Neues: https://lea.test/herbst\n\nBis bald", 'knopf_text' => 'Zum Programm', 'knopf_url' => 'https://lea.test/programm', 'tags' => ['newsletter', 'club'], 'created_by' => $this->lea->id]));

        // Test an zwei Adressen, ohne Zaehlung
        $this->assertSame(2, $this->in(fn () => app(Versand::class)->test($n, ['lea@example.com', ' Anna@test.ch ', 'kaputt'])));
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->test && $m->hasTo('lea@example.com') && str_starts_with($m->envelope()->subject, '[Test] Hallo du'));
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->hasTo('anna@test.ch') && $m->envelope()->subject === '[Test] Hallo Anna');
        $this->assertSame(['lea@example.com', 'anna@test.ch'], $n->fresh()->settings['testadressen']);
        Mail::fake();

        // Start: nur bestaetigte mit passendem Tag (Anna, Bea), nicht Carla (abgemeldet), nicht Dora (wartet)
        $this->assertSame(2, $this->in(fn () => app(Versand::class)->starten($n)));
        $this->assertSame('laeuft', $n->fresh()->status);
        $this->assertSame(1, $this->in(fn () => app(Versand::class)->welle(1)), 'Welle von 1');
        $this->assertSame('laeuft', $n->fresh()->status);
        $this->artisan('newsletter:lauf', ['was' => 'wellen'])->assertSuccessful();
        $n = $n->fresh();
        $this->assertSame('gesendet', $n->status);
        $this->assertSame(2, $n->gesendet);
        Mail::assertSentTimes(NewsletterMail::class, 2);

        $v = $this->in(fn () => NewsletterVersand::where('kontakt_id', $anna->id)->first());
        $html = $this->in(fn () => (new NewsletterMail($n, $anna, $v))->render());
        $this->assertStringContainsString('Liebe Anna', $html);
        $this->assertStringContainsString('/n/o/'.$v->token.'.gif', $html, 'Zaehlpixel');
        $this->assertStringContainsString('/n/k/'.$v->token.'?u=https%3A%2F%2Flea.test%2Fherbst', $html, 'Klick ueber die Zaehlung');
        $this->assertStringContainsString('/n/abmelden/'.$anna->token, $html);
        $this->assertStringContainsString('/n/w/'.$v->token, $html, 'Webversion');
        $this->assertStringContainsString('/n/abmelden/'.$anna->token, (string) ((new NewsletterMail($n, $anna, $v))->headers()->text['List-Unsubscribe'] ?? ''), 'Kopfzeile');

        $this->get('http://a.test/n/o/'.$v->token.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get('http://a.test/n/k/'.$v->token.'?u=https://lea.test/herbst')->assertRedirect('https://lea.test/herbst');
        $this->get('http://a.test/n/k/'.$v->token.'?u=javascript:alert(1)')->assertNotFound();
        $this->get('http://a.test/n/k/'.$v->token.'?u=https://lea.test/herbst')->assertRedirect();
        $n = $n->fresh();
        $this->assertSame(1, $n->geoeffnet);
        $this->assertSame(1, $n->geklickt, 'nur einmal gezaehlt');
        $this->get('http://a.test/n/w/'.$v->token)->assertOk()->assertSee('Neues im Herbst')->assertSee('Liebe Anna');

        // Gesendet: nichts mehr aendern, kein zweiter Start
        $this->assertSame(0, $this->in(fn () => app(Versand::class)->starten($n)));
        // Geplant: Welle startet ihn, sobald die Zeit da ist
        $g = $this->in(fn () => Newsletter::create(['betreff' => 'Später', 'text' => 'x', 'status' => 'geplant', 'geplant_at' => now()->addHour()]));
        $this->in(fn () => app(Versand::class)->welle());
        $this->assertSame('geplant', $g->fresh()->status);
        $this->travel(61)->minutes();
        $this->in(fn () => app(Versand::class)->welle());
        $this->assertSame('gesendet', $g->fresh()->status);
        $this->assertSame(2, $g->fresh()->empfaenger);
    }

    public function test_impuls_wird_zum_newsletter_entwurf(): void
    {
        $this->lea->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        $post = $this->in(fn () => Post::create(['title' => 'Herbst-Impuls', 'slug' => 'herbst-impuls', 'type' => 'impuls', 'excerpt' => 'Kurz gesagt: atmen.', 'body' => '<p>Langer Text</p>', 'image_url' => 'https://lea.test/bild.jpg', 'url' => 'https://lea.test/blog/herbst', 'is_published' => true, 'published_at' => now()]));
        $this->in(fn () => Livewire::actingAs($this->lea)->test(EditPost::class, ['record' => $post->id])->callAction('newsletter'));
        $n = $this->in(fn () => Newsletter::first());
        $this->assertSame('Herbst-Impuls', $n->betreff);
        $this->assertSame('https://lea.test/bild.jpg', $n->bild_url);
        $this->assertSame('https://lea.test/blog/herbst', $n->knopf_url);
        $this->assertSame(['newsletter'], $n->tags);
        $this->assertSame('entwurf', $n->status);
        $this->assertStringContainsString('atmen', $n->text);
    }

    public function test_website_legt_newsletter_ueber_die_api_an(): void
    {
        $this->in(fn () => app(Kontakte::class)->anmelden('anna@test.ch', 'Anna', ['newsletter'], [], false));
        $token = $this->lea->createToken('website', ['lesen', 'mcp'])->plainTextToken;
        $this->withToken($token)->getJson('http://a.test/api/v1/newsletter/tags')->assertOk()->assertJson(['bestaetigt' => 1, 'tags' => [['tag' => 'newsletter', 'anzahl' => 1]]]);
        $this->app['auth']->forgetGuards();
        $r = $this->withToken($token)->postJson('http://a.test/api/v1/newsletter', ['betreff' => 'Neuer Beitrag', 'text' => "Kurz gesagt.\n\nWeiterlesen: https://lea.test/blog/x", 'bild_url' => 'https://lea.test/b.jpg', 'knopf_url' => 'https://lea.test/blog/x', 'tags' => ['newsletter'], 'senden' => true, 'quelle' => 'wordpress:12'])
            ->assertCreated();
        $this->assertSame(1, $r->json('gestartet'));
        $this->assertSame('laeuft', $r->json('status'));
        // Ohne Team-Rolle oder ohne Faehigkeit mcp: nein
        $this->app['auth']->forgetGuards();
        $lese = $this->lea->createToken('nur-lesen', ['lesen'])->plainTextToken;
        $this->withToken($lese)->getJson('http://a.test/api/v1/newsletter/tags')->assertForbidden();
    }

    public function test_mitglied_wird_kontakt_mit_tag_und_mandanten_sehen_sich_nicht(): void
    {
        $nora = User::factory()->create(['name' => 'Nora', 'email' => 'nora@test.ch']);
        $this->a->users()->attach($nora, ['role' => Role::Member->value, 'status' => 'active']);
        $offer = $this->in(fn () => Offer::create(['title' => 'Herbstkurs', 'slug' => 'herbstkurs', 'is_active' => true]));
        $this->in(fn () => app(Zugang::class)->grant($nora, $offer, 'manual', 'x', notify: false));
        $k = $this->in(fn () => Kontakt::where('email', 'nora@test.ch')->first());
        $this->assertSame('bestaetigt', $k->status);
        $this->assertSame($nora->id, $k->user_id);
        $this->assertSame(['kundin', 'herbstkurs'], $k->tags);

        $this->assertSame(0, $this->in(fn () => Kontakt::count(), $this->b));
        $this->assertSame(0, $this->in(fn () => Newsletter::count(), $this->b));
        $this->get('http://b.test/n/abmelden/'.$k->token)->assertNotFound();

        $this->lea->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        $this->actingAs($this->lea)->get('http://a.test/coach/kontakte')->assertOk()->assertSee('nora@test.ch')->assertSee('app_anmelden');
        $this->actingAs($this->lea)->get('http://a.test/coach/newsletter')->assertOk();
        $this->actingAs($this->lea)->get('http://a.test/coach/serien')->assertOk();
        $this->get('http://a.test/newsletter/anmelden?tag=live-abend&zurueck=https://lea.test/x')->assertOk()->assertSee('value="live-abend"', false)->assertSee('https://lea.test/x');
    }

    public function test_abmelden_ohne_token_schickt_den_link(): void
    {
        Mail::fake();
        $k = $this->in(fn () => app(Kontakte::class)->anmelden('doris@example.com', 'Doris', ['newsletter'], [], false));
        $this->get('http://a.test/n/abmelden')->assertOk()->assertSee('Abmeldelink schicken');
        $this->post('http://a.test/n/abmelden', ['email' => 'Doris@example.com'])->assertOk()->assertSee('hast du gleich Post');
        Mail::assertSent(AbmeldeLinkMail::class, fn ($m) => $m->hasTo('doris@example.com') && str_contains($m->url, '/n/abmelden/'.$k->token));
        // Unbekannte Adresse: gleiche Antwort, keine Mail
        $this->post('http://a.test/n/abmelden', ['email' => 'niemand@example.com'])->assertOk()->assertSee('hast du gleich Post');
        Mail::assertSent(AbmeldeLinkMail::class, 1);
    }

    public function test_baukasten_rendert_bausteine_vorschau_und_formulare(): void
    {
        $this->lea->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        $this->a->forceFill(['settings' => array_merge($this->a->settings, ['newsletter' => ['social' => ['instagram' => 'https://instagram.com/lea']], 'mail' => ['fusszeile' => 'Musterstrasse 1']])])->save();
        $offer = $this->in(fn () => Offer::create(['slug' => 'club', 'title' => 'Clubzugang', 'type' => 'club', 'is_active' => true, 'settings' => ['sichtbar' => true, 'preis_chf' => 49, 'teaser' => 'Jeden Monat dabei.']]));
        $bloecke = [
            ['type' => 'ueberschrift', 'data' => ['text' => 'Hallo {vorname}', 'groesse' => 'gross']],
            ['type' => 'text', 'data' => ['html' => '<p>Ein <strong>Absatz</strong> mit <a href="https://lea.test/blog">Link</a><script>alert(1)</script></p><ul><li>Punkt</li></ul>']],
            ['type' => 'bild', 'data' => ['datei' => 'tenants/1/newsletter/abc.jpg', 'alt' => 'Lea', 'breite' => 'klein', 'link' => 'https://lea.test']],
            ['type' => 'knopf', 'data' => ['text' => 'Jetzt buchen', 'url' => 'https://lea.test/buchen', 'stil' => 'leise', 'ausrichtung' => 'links']],
            ['type' => 'trenner', 'data' => ['art' => 'linie']],
            ['type' => 'zitat', 'data' => ['text' => 'Weniger ist mehr.', 'von' => 'Lea']],
            ['type' => 'kasten', 'data' => ['titel' => 'Termin', 'html' => '<p>Freitag, 20 Uhr</p>']],
            ['type' => 'angebot', 'data' => ['offer_id' => $offer->id, 'knopf_text' => 'Dabei sein']],
        ];
        $n = $this->in(fn () => Newsletter::create(['betreff' => 'Baukasten', 'text' => ' ', 'bloecke' => $bloecke, 'created_by' => $this->lea->id]));
        $anna = $this->in(fn () => app(Kontakte::class)->anmelden('anna@example.com', 'Anna', ['newsletter'], [], false));

        $html = $this->in(fn () => (new NewsletterMail($n, $anna, null))->render());
        $this->assertStringContainsString('Hallo Anna', $html);
        $this->assertStringContainsString('<strong>Absatz</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('/n/bild/abc.jpg', $html);
        $this->assertStringContainsString('max-width:280px', $html);
        $this->assertStringContainsString('Jetzt buchen', $html);
        $this->assertStringContainsString('text-align:left', $html);
        $this->assertStringContainsString('Weniger ist mehr.', $html);
        $this->assertStringContainsString('Freitag, 20 Uhr', $html);
        $this->assertStringContainsString('Clubzugang', $html);
        $this->assertStringContainsString('49.00 CHF', $html);
        $this->assertStringContainsString('/kaufen/club', $html);
        $this->assertStringContainsString('Instagram', $html);
        $this->assertStringContainsString('/img/social/instagram.png', $html, 'Social-Icon im Fuss');
        $this->assertStringContainsString('Musterstrasse 1', $html);
        $this->assertStringContainsString('/n/abmelden/'.$anna->token, $html);

        // Mit Versand laufen Links ueber die Klickzaehlung
        $v = $this->in(fn () => NewsletterVersand::create(['newsletter_id' => $n->id, 'kontakt_id' => $anna->id, 'token' => str_repeat('k', 40)]));
        $html2 = $this->in(fn () => (new NewsletterMail($n, $anna, $v))->render());
        $this->assertStringContainsString('/n/k/'.$v->token, $html2);

        // Klartext und Headline aus den Bausteinen, alte Felder werden Bausteine
        $this->assertSame('Hallo {vorname}', Bausteine::titel($bloecke));
        $this->assertStringContainsString('Punkt', Bausteine::text($bloecke));
        $alt = Bausteine::ausAlt('https://lea.test/b.jpg', 'Titel', "Absatz eins\n\n[Mehr](https://lea.test)", 'Los', 'https://lea.test/los');
        $this->assertSame(['bild', 'ueberschrift', 'text', 'knopf'], array_column($alt, 'type'));
        $this->assertStringContainsString('<a href="https://lea.test">Mehr</a>', $alt[2]['data']['html']);

        // Vorschauen fuer das Team, Bild-Route
        Storage::fake('local');
        Storage::disk('local')->put('tenants/'.$this->a->id.'/newsletter/abc.jpg', 'bild');
        $this->actingAs($this->lea)->get('http://a.test/coach-vorschau/newsletter/'.$n->id)->assertOk()->assertSee('Hallo Lea');
        $this->actingAs($this->lea)->get('http://a.test/coach-vorschau/layout')->assertOk()->assertSee('Grundlayout');
        $this->get('http://a.test/n/bild/abc.jpg')->assertOk();
        $this->get('http://a.test/n/bild/../x.jpg')->assertNotFound();
        $serie = $this->in(fn () => Serie::create(['titel' => 'Willkommen', 'tag' => 'newsletter', 'aktiv' => true, 'schritte' => [['tage' => 0, 'betreff' => 'Hallo', 'bloecke' => [['type' => 'ueberschrift', 'data' => ['text' => 'Willkommen, {vorname}']]]]]]));
        $this->actingAs($this->lea)->get('http://a.test/coach-vorschau/serie/'.$serie->id.'/0')->assertOk()->assertSee('Willkommen, Lea');
        $bea = User::factory()->create();
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->actingAs($bea)->get('http://a.test/coach-vorschau/newsletter/'.$n->id)->assertForbidden();

        // Serienmail mit Bausteinen
        $this->in(fn () => app(Serien::class)->ausloesen($anna, 'newsletter'));
        Mail::assertSent(NewsletterMail::class, fn ($m) => str_contains($m->render(), 'Willkommen, Anna'));

        // Formulare im Coach-Bereich laden mit dem Baukasten
        $this->in(fn () => Livewire::actingAs($this->lea)->test(EditNewsletter::class, ['record' => $n->id])->assertOk()->assertSee('Baustein hinzufügen'));
        $this->in(fn () => Livewire::actingAs($this->lea)->test(EditSerie::class, ['record' => $serie->id])->assertOk()->assertSee('app_anmelden tag=', false)->assertSee('So bekommt jemand den Tag'));
        $this->in(fn () => Livewire::actingAs($this->lea)->test(Einstellungen::class)->assertOk()->assertSee('Grundlayout'));
    }
}

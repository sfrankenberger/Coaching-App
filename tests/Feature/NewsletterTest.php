<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Resources\Posts\Pages\EditPost;
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
use App\Newsletter\Kontakte;
use App\Newsletter\Serien;
use App\Newsletter\Versand;
use App\Shop\Zugang;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Etappe 11: Kontakte mit Double-Opt-in, Tags, Newsletter in Wellen mit Zaehlung, Serien, Abmeldung, Isolation. */
class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

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
}

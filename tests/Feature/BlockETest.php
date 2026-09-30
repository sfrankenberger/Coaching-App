<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Pages\Rundnachricht;
use App\Http\Controllers\Auth\SocialController;
use App\Mail\EmailWechselMail;
use App\Mail\WillkommenMail;
use App\Models\Event;
use App\Models\Offer;
use App\Models\Post;
use App\Models\Program;
use App\Models\PushSubscription;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Verkauf;
use App\Notifications\AppNotification;
use App\Notifications\Nachricht;
use App\Notifications\Runden;
use App\Notifications\WebPushChannel;
use App\Shop\Zahlen;
use App\Shop\Zugang;
use App\Support\Altlinks;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/** Block E: Anmeldedienste verknuepfen, Mailadresse wechseln, Test-Push, Zahlen, Altlinks, Mails. */
class BlockETest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['oauth' => ['google' => ['client_id' => 'g-id', 'client_secret' => 'g-secret'], 'apple' => ['client_id' => 'ch.app', 'team_id' => 'T1', 'key_id' => 'K1', 'private_key' => 'pem']]]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach', 'email' => 'lea@example.com']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel', 'email' => 'bea@example.com']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'joined_at' => now()->subMonths(3)]);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active', 'joined_at' => now()->subMonth()]);
        foreach ([$this->lea, $this->anna, $this->bea] as $u) {
            $u->membershipIn($this->a)->forceFill(['settings' => ['onboarding_seen_at' => now()->toDateTimeString()]])->save();
        }
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function dienstLiefert(string $id, ?string $email, string $name = 'Anna'): void
    {
        $social = (new SocialUser)->map(['id' => $id, 'email' => $email, 'name' => $name]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($social);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect()->to('https://dienst.test/auth'));
        Socialite::shouldReceive('driver')->andReturn($provider);
    }

    public function test_anmeldeseite_zeigt_google_und_apple_wenn_hinterlegt(): void
    {
        $this->get('http://a.test/anmelden')->assertOk()->assertSee('Google')->assertSee('Apple');
        $this->a->forceFill(['settings' => ['oauth' => ['apple' => ['client_id' => 'ch.app']]]])->save();   // Apple ohne Schluessel: kein Knopf
        $this->assertSame([], SocialController::availableProviders($this->a->fresh()));
    }

    public function test_anmelden_ueber_dienst_merkt_die_kennung_und_findet_sie_spaeter_ohne_mail(): void
    {
        $this->dienstLiefert('apple-123', 'anna@example.com');
        $this->get('http://a.test/anmelden/dienst/apple/zurueck')->assertRedirect('http://a.test');
        $this->assertAuthenticatedAs($this->anna);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $this->anna->id, 'provider' => 'apple', 'provider_id' => 'apple-123']);

        // Beim naechsten Mal verbirgt Apple die Adresse: die Kennung reicht.
        auth()->logout();
        Mockery::close();
        $this->dienstLiefert('apple-123', 'xyz@privaterelay.appleid.com');
        $this->get('http://a.test/anmelden/dienst/apple/zurueck')->assertRedirect('http://a.test');
        $this->assertAuthenticatedAs($this->anna);
    }

    public function test_versteckte_apple_adresse_ohne_verknuepfung_bekommt_erklaerung(): void
    {
        $this->dienstLiefert('apple-999', 'abc@privaterelay.appleid.com');
        $this->get('http://a.test/anmelden/dienst/apple/zurueck')->assertRedirect('http://a.test/anmelden')
            ->assertSessionHas('fehler', fn ($f) => str_contains($f, 'verborgen'));
        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_im_profil_verknuepfen_und_trennen(): void
    {
        $this->dienstLiefert('g-777', 'anna.privat@gmail.com');
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertSee('Anmelden mit Google oder Apple')->assertSee('Verknüpfen');
        $this->actingAs($this->anna)->get('http://a.test/profil/dienst/google/verknuepfen')->assertRedirect('https://dienst.test/auth');
        $this->actingAs($this->anna)->withSession(['dienst.verknuepfen' => $this->anna->id])->get('http://a.test/anmelden/dienst/google/zurueck')
            ->assertRedirect('http://a.test/profil#anmelden')->assertSessionHas('meldung');
        $this->assertDatabaseHas('social_accounts', ['user_id' => $this->anna->id, 'provider' => 'google', 'provider_id' => 'g-777', 'email' => 'anna.privat@gmail.com']);
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertSee('verknüpft als anna.privat@gmail.com')->assertSee('Trennen');

        // Bea kann dasselbe Google-Konto nicht auch nehmen
        $this->actingAs($this->bea)->withSession(['dienst.verknuepfen' => $this->bea->id])->get('http://a.test/anmelden/dienst/google/zurueck')
            ->assertSessionHas('fehler', fn ($f) => str_contains($f, 'anderen Person'));
        $this->assertSame($this->anna->id, SocialAccount::where('provider_id', 'g-777')->first()->user_id);

        $this->actingAs($this->anna)->delete('http://a.test/profil/dienst/google')->assertRedirect('http://a.test/profil#anmelden');
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_mailadresse_wechseln_mit_bestaetigung(): void
    {
        Mail::fake();
        $this->actingAs($this->anna)->post('http://a.test/profil/email', ['email' => 'Neu@Example.com'])->assertRedirect('http://a.test/profil#email')->assertSessionHas('meldung');
        $this->assertSame('anna@example.com', $this->anna->fresh()->email);   // noch nichts passiert
        $url = null;
        Mail::assertSent(EmailWechselMail::class, function (EmailWechselMail $m) use (&$url) {
            $url = $m->url;

            return $m->hasTo('neu@example.com') && $m->neu === 'neu@example.com';
        });
        $this->assertStringContainsString('/profil/email/bestaetigen/', $url);

        // Bea mit demselben Link: falsches Konto
        $this->actingAs($this->bea)->get($url)->assertRedirect('http://a.test/profil')->assertSessionHas('fehler');
        $this->assertSame('anna@example.com', $this->anna->fresh()->email);

        $this->actingAs($this->anna)->get($url)->assertRedirect('http://a.test/profil')->assertSessionHas('meldung');
        $this->assertSame('neu@example.com', $this->anna->fresh()->email);
        $this->assertNotNull($this->anna->fresh()->email_verified_at);

        // Adresse einer anderen Person geht nicht, kaputte Signatur auch nicht
        $this->actingAs($this->anna)->post('http://a.test/profil/email', ['email' => 'bea@example.com'])->assertSessionHas('fehler');
        $this->actingAs($this->anna)->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    }

    public function test_test_push_nur_mit_geraet(): void
    {
        Notification::fake();
        $this->actingAs($this->anna)->postJson('http://a.test/push/test')->assertOk()->assertJson(['ok' => false]);
        Notification::assertNothingSent();

        $this->in(fn () => PushSubscription::create(['user_id' => $this->anna->id, 'endpoint' => 'https://push.example/1', 'endpoint_hash' => hash('sha256', 'https://push.example/1'), 'p256dh' => 'x', 'auth' => 'y']));
        $this->actingAs($this->anna)->postJson('http://a.test/push/test')->assertOk()->assertJson(['ok' => true]);
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $n->nachricht->tag === 'push-test' && $channels === [WebPushChannel::class]);
        $this->assertDatabaseCount('notifications', 0);
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertSee('Test schicken');
    }

    protected function verkaeufe(): void
    {
        $this->in(function () {
            $paket = Offer::create(['title' => 'Paket', 'is_active' => true]);
            $kurs = Offer::create(['title' => 'Kurs', 'is_active' => true]);
            Verkauf::create(['user_id' => $this->anna->id, 'offer_id' => $paket->id, 'title' => 'Paket', 'betrag' => 1200, 'zahlungsart' => 'rechnung', 'status' => 'bezahlt', 'created_at' => now()->subMonths(2)]);
            Verkauf::create(['user_id' => $this->anna->id, 'offer_id' => $kurs->id, 'title' => 'Kurs', 'betrag' => 300, 'zahlungsart' => 'stripe', 'status' => 'bezahlt', 'created_at' => now()->subDays(2)]);
            Verkauf::create(['user_id' => $this->bea->id, 'offer_id' => $kurs->id, 'title' => 'Kurs', 'betrag' => 300, 'zahlungsart' => 'rechnung', 'status' => 'offen', 'faellig_am' => now()->subDays(5)->toDateString(), 'created_at' => now()->subDays(40)]);
            Verkauf::create(['user_id' => $this->bea->id, 'offer_id' => $kurs->id, 'title' => 'Kurs', 'betrag' => 300, 'zahlungsart' => 'rechnung', 'status' => 'storniert', 'created_at' => now()->subDays(1)]);
            Verkauf::create(['user_id' => $this->bea->id, 'offer_id' => $paket->id, 'title' => 'Paket', 'betrag' => 100, 'waehrung' => 'EUR', 'zahlungsart' => 'bezahlt', 'status' => 'bezahlt', 'created_at' => now()->subDays(3)]);
        });
    }

    public function test_zahlen_fuer_die_inhaberin(): void
    {
        $this->travelTo(now()->setDate(now()->year, 6, 15)->setTime(12, 0));
        $this->verkaeufe();
        $z = $this->in(fn () => app(Zahlen::class)->uebersicht());
        $this->assertSame(['CHF' => 1800.0, 'EUR' => 100.0], $z['jahr']);
        $this->assertSame(['CHF' => 300.0, 'EUR' => 100.0], $z['monat']);
        $this->assertSame(['CHF' => 300.0], $z['offen']);
        $this->assertSame(1, $z['ueberfaellig']);
        $this->assertSame('Paket', $z['angebote']->first()['titel']);
        $this->assertSame($this->anna->id, $z['beste']->first()['user']->id);
        $this->assertSame(1, $z['beste']->first()['rang']);

        $p = $this->in(fn () => app(Zahlen::class)->person($this->bea));
        $this->assertSame(['CHF' => 300.0, 'EUR' => 100.0], $p['summen']);
        $this->assertSame(2, $p['rang']);
        $this->assertSame(1, $p['offen_anzahl']);
        $this->assertStringContainsString('überfällig', $p['hinweis']);
        $this->assertSame(['Paket', 'Kurs'], $p['wofuer']);

        $this->actingAs($this->lea)->get('http://a.test/coach/zahlen')->assertOk()->assertSee("1'800.00 CHF")->assertSee('Beste Kundinnen')->assertSee('Anna Muster');
        $this->actingAs($this->anna)->get('http://a.test/coach/zahlen')->assertForbidden();

        // Dossier: Kennzahlen im Reiter Rechnungen, auch ohne bexio
        $m = $this->bea->membershipIn($this->a);
        $this->actingAs($this->lea)->get('http://a.test/coachees/'.$m->id.'?r=rechnungen')->assertOk()->assertSee('2 von 2')->assertSee('Wofür bezahlt: Paket, Kurs');
    }

    public function test_altlinks_landen_auf_der_richtigen_seite(): void
    {
        [$p, $u, $e, $post] = $this->in(function () {
            $p = Program::create(['slug' => 'atem', 'title' => 'Atem', 'type' => 'hybrid', 'legacy_id' => '41']);
            $u = Unit::create(['program_id' => $p->id, 'title' => 'Einheit', 'position' => 1, 'legacy_id' => '900']);
            $e = Event::create(['title' => 'Call', 'starts_at' => now()->addDay(), 'legacy_id' => '55']);
            $post = Post::create(['title' => 'Impuls', 'slug' => 'ruhe', 'type' => 'impuls', 'is_published' => true, 'published_at' => now(), 'legacy_id' => '77']);

            return [$p, $u, $e, $post];
        });
        $alt = new class($this->a, fn ($fn) => $this->in($fn)) extends Altlinks
        {
            public function __construct(Tenant $t, protected \Closure $in)
            {
                parent::__construct($t);
            }

            public function ziel(string $pfad, array $query = []): string
            {
                return ($this->in)(fn () => parent::ziel($pfad, $query));
            }
        };
        $this->assertSame('/', $alt->ziel(''));
        $this->assertSame('/termine', $alt->ziel('/mitgliederbereich/termine/'));
        $this->assertSame('/termine/'.$e->id, $alt->ziel('termine/55'));
        $this->assertSame('/kurse/atem', $alt->ziel('kurs/atem'));
        $this->assertSame('/kurse/atem', $alt->ziel('kurse/41'));
        $this->assertSame('/kurse/atem/einheit/'.$u->id, $alt->ziel('kursraum', ['kurs' => 'atem', 'lektion' => '900']));
        $this->assertSame('/kurse/atem/einheit/'.$u->id, $alt->ziel('lektion/900'));
        $this->assertSame('/kurse/atem/schritt/2', $alt->ziel('kurs/atem/woche/2'));
        $this->assertSame('/kurse/atem/austausch', $alt->ziel('kurs/atem/austausch'));
        $this->assertSame('/impulse/'.$post->slug, $alt->ziel('', ['p' => '77']));
        $this->assertSame('/impulse/'.$post->slug, $alt->ziel('impulse/ruhe'));
        $this->assertSame('/gespraech', $alt->ziel('chat/12'));
        $this->assertSame('/profil', $alt->ziel('mitgliedschaft'));
        $this->assertSame('/', $alt->ziel('irgendwas/unbekannt'));

        // Eigene Zuordnung des Mandanten gewinnt
        $this->a->forceFill(['settings' => array_merge($this->a->settings, ['altlinks' => ['goldnuggets' => '/kurse/atem', 'goldnuggets/liste' => '/material']])])->save();
        $alt = new Altlinks($this->a->fresh());
        $this->assertSame('/kurse/atem', $alt->ziel('goldnuggets/2024'));   // ohne Datenbank, geht auch ohne Mandantenkontext
        $this->assertSame('/material', $alt->ziel('goldnuggets/liste/alle'));

        // Ohne Anmeldung ueber /anmelden mit weiter, angemeldet direkt (301)
        $this->get('http://a.test/mitgliederbereich/termine/55')->assertRedirect('http://a.test/anmelden?weiter=%2Ftermine%2F'.$e->id);
        $this->get('http://a.test/mitgliederbereich/')->assertRedirect('http://a.test/anmelden');
        $this->actingAs($this->anna)->get('http://a.test/mitgliederbereich/kurs/atem')->assertStatus(301)->assertRedirect('http://a.test/kurse/atem');
    }

    public function test_willkommensmail_mit_text_je_programm_und_art(): void
    {
        Mail::fake();
        $offer = $this->in(function () {
            $p = Program::create(['slug' => 'eins', 'title' => 'Eins zu eins', 'type' => 'one_on_one', 'settings' => ['willkommen_text' => "Ich freue mich auf dich.\nBis bald im Gespräch."]]);
            $o = Offer::create(['title' => '1:1', 'type' => 'one_on_one', 'is_active' => true]);
            $o->programs()->attach($p, ['tenant_id' => $this->a->id]);

            return $o;
        });
        $this->in(fn () => app(Zugang::class)->welcome($this->anna, $offer));
        Mail::assertSent(WillkommenMail::class, fn (WillkommenMail $m) => $m->text === "Ich freue mich auf dich.\nBis bald im Gespräch." && $m->art === 'one_on_one');
        $html = $this->in(fn () => (new WillkommenMail($this->anna, 'http://a.test/x', '1:1', null, 'one_on_one'))->render());
        $this->assertStringContainsString('unser Gespräch', $html);
        $this->assertStringContainsString('Einführung', $html);
        $this->assertStringContainsString('Startbildschirm', $html);
    }

    public function test_abendmail_hat_karten_mit_links(): void
    {
        Notification::fake();
        $this->in(function () {
            for ($i = 1; $i <= 8; $i++) {
                Post::create(['title' => "Impuls $i", 'slug' => "impuls-$i", 'type' => 'impuls', 'is_published' => true, 'published_at' => now()->subHours(9 - $i)]);
            }
            app(Runden::class)->abendmail();
        });
        Notification::assertSentTo($this->anna, AppNotification::class, function (AppNotification $n, array $channels) {
            return $channels === ['mail'] && count($n->nachricht->liste) === Runden::ABENDMAIL_KARTEN
                && $n->nachricht->liste[0]['titel'] === 'Impuls 8' && str_contains($n->nachricht->liste[0]['url'], '/impulse/')
                && str_contains($n->nachricht->text, 'Ausserdem') && str_contains($n->nachricht->text, 'Impuls 1');
        });
        $html = $this->in(fn () => view('mail.nachricht', ['user' => $this->anna, 'nachricht' => new Nachricht(titel: 'T', text: 'x', url: 'http://a.test/', liste: [['titel' => 'Eins', 'text' => 'kurz', 'herkunft' => 'Impuls', 'url' => 'http://a.test/impulse/1']]), 'appName' => 'A', 'branding' => app(Branding::class)])->render());
        $this->assertStringContainsString('Ansehen und antworten', $html);
        $this->assertStringContainsString('in deinem Profil ein', $html);
    }

    public function test_rundnachricht_fragt_vor_dem_senden_nach(): void
    {
        $this->actingAs($this->lea)->get('http://a.test/coach/rundnachricht')->assertOk()->assertSee('fragt die App nochmals nach');
        $c = $this->in(fn () => Livewire::actingAs($this->lea)->test(Rundnachricht::class)->fillForm(['an' => 'alle', 'titel' => 'Hallo zusammen', 'text' => 'Bis Montag', 'kanaele' => ['push', 'mail']]));
        $text = $this->in(fn () => $c->instance()->vorschau());
        $this->assertStringContainsString('Geht an 2 Personen', $text);
        $this->assertStringContainsString('Push/Telegram und Mail', $text);
        $this->assertStringContainsString('«Hallo zusammen»', $text);
        $this->assertStringContainsString('nicht rückgängig', $text);
    }
}

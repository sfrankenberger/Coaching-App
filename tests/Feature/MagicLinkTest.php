<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);

        $this->anna = User::factory()->create(['email' => 'anna@example.com', 'password' => null]);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function linkAus(): string
    {
        $url = null;
        Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
            $url = $mail->url;

            return $mail->hasTo('anna@example.com');
        });

        return $url;
    }

    public function test_link_anfordern_und_anmelden(): void
    {
        Mail::fake();

        $this->post('http://a.test/anmelden/link', ['email' => 'Anna@Example.com', 'weiter' => '/profil'])
            ->assertOk()->assertSee('Schau in dein Postfach');

        $url = $this->linkAus();
        $this->assertStringStartsWith('http://a.test/anmelden/', $url);

        $this->get($url)->assertRedirect('/profil');
        $this->assertAuthenticatedAs($this->anna);

        // zweite Verwendung geht nicht mehr
        auth()->logout();
        $this->get($url)->assertRedirect('http://a.test/anmelden');
        $this->assertGuest();
    }

    public function test_unbekannte_adresse_bekommt_keine_mail_aber_dieselbe_antwort(): void
    {
        Mail::fake();

        $this->post('http://a.test/anmelden/link', ['email' => 'niemand@example.com'])
            ->assertOk()->assertSee('Schau in dein Postfach');

        Mail::assertNothingSent();
    }

    public function test_ohne_mitgliedschaft_im_mandanten_gibt_es_keinen_link(): void
    {
        Mail::fake();

        $this->post('http://b.test/anmelden/link', ['email' => 'anna@example.com'])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_link_von_mandant_a_gilt_nicht_auf_mandant_b(): void
    {
        Mail::fake();
        $this->b->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);

        $this->post('http://a.test/anmelden/link', ['email' => 'anna@example.com']);
        $url = str_replace('http://a.test/', 'http://b.test/', $this->linkAus());

        $this->get($url)->assertRedirect('http://b.test/anmelden');
        $this->assertGuest();
        $this->assertSame(1, app(CurrentTenant::class)->run($this->a, fn () => LoginToken::count()));
        $this->assertSame(0, app(CurrentTenant::class)->run($this->b, fn () => LoginToken::count()));
    }

    public function test_abgelaufener_link_geht_nicht(): void
    {
        Mail::fake();
        $this->post('http://a.test/anmelden/link', ['email' => 'anna@example.com']);
        $url = $this->linkAus();

        $this->travel(16)->minutes();
        $this->get($url)->assertRedirect('http://a.test/anmelden');
        $this->assertGuest();
    }

    public function test_passwort_ist_freiwillig_und_geht_wenn_gesetzt(): void
    {
        $this->post('http://a.test/anmelden/passwort', ['email' => 'anna@example.com', 'password' => 'irgendwas'])
            ->assertSessionHasErrors('password');
        $this->assertGuest();

        $this->anna->forceFill(['password' => 'geheim-geheim'])->save();

        $this->post('http://a.test/anmelden/passwort', ['email' => 'anna@example.com', 'password' => 'geheim-geheim'])
            ->assertRedirect('http://a.test');
        $this->assertAuthenticatedAs($this->anna);
    }

    public function test_angemeldet_ohne_mitgliedschaft_wird_abgemeldet(): void
    {
        $this->actingAs($this->anna)->get('http://b.test/')->assertRedirect('http://b.test/anmelden');
        $this->assertGuest();
    }

    public function test_startseite_und_profil_fuer_mitglied(): void
    {
        $this->actingAs($this->anna)->get('http://a.test/')->assertRedirect('http://a.test/willkommen');
        $this->actingAs($this->anna)->get('http://a.test/?ohne=1')->assertOk()->assertSee('Hallo');

        $this->actingAs($this->anna)->post('http://a.test/profil', ['name' => 'Anna Muster', 'phone' => '079 123 45 67'])->assertRedirect();
        $this->assertSame('079 123 45 67', $this->anna->fresh()->phone);

        $this->actingAs($this->anna)->post('http://a.test/profil/benachrichtigungen', ['termine' => 1])->assertRedirect();
        $this->assertSame(['termine' => true, 'abendmail' => false, 'aufgaben' => false], $this->anna->membershipIn($this->a)->setting('notifications'));
    }

    public function test_gast_wird_zur_anmeldung_geschickt(): void
    {
        $this->get('http://a.test/profil')->assertRedirect('http://a.test/anmelden?weiter=%2Fprofil');
    }
}

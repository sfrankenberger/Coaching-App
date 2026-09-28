<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Enums\Role;
use App\Mail\WillkommenMail;
use App\Models\CoachNote;
use App\Models\Entitlement;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Das Dossier in der App-Huelle: Kopf, Pakete, Verkaufen, Reiter, und alles, was die Coachin dort tut. */
class DossierAppTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected Membership $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'phone' => '+41791112233']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->m = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first());
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function url(string $rest = ''): string
    {
        return "http://a.test/coachees/{$this->m->id}{$rest}";
    }

    public function test_dossier_kopf_reiter_und_zugriff(): void
    {
        $this->in(function () {
            $conv = app(Chat::class)->directFor($this->anna);
            Message::create(['conversation_id' => $conv->id, 'user_id' => $this->anna->id, 'body' => 'Hallo Lea, wie geht es weiter?']);
            $conv->forceFill(['last_message_at' => now()])->save();
        });
        $r = $this->actingAs($this->lea)->get($this->url())->assertOk();
        $r->assertSee('Anna Muster')->assertSee('WhatsApp')->assertSee('Anrufen')->assertSee('Etwas verkaufen')->assertSee('Noch kein Paket')
            ->assertSee('Nächster Termin')->assertSee('Wochenaufgaben')->assertSee('Bei den Calls')->assertSee('Sie schreibt')
            ->assertSee('Hallo Lea, wie geht es weiter?')->assertSee('An Anna schreiben')->assertSee('Als gelesen');
        $this->actingAs($this->anna)->get($this->url())->assertForbidden();

        // Anderer Mandant sieht das Dossier nicht
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $mia = User::factory()->create(['name' => 'Mia B']);
        $b->users()->attach($mia, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->actingAs($mia)->get("http://b.test/coachees/{$this->m->id}")->assertNotFound();
    }

    public function test_nachricht_notiz_aufgabe_termin_und_vorschlag(): void
    {
        $this->actingAs($this->lea)->post($this->url('/nachricht'), ['body' => 'Liebe Anna, gern!'])->assertRedirect($this->url());
        $this->assertSame(1, $this->in(fn () => Message::where('user_id', $this->lea->id)->where('body', 'Liebe Anna, gern!')->count()));

        $this->actingAs($this->lea)->post($this->url('/notiz'), ['body' => 'Mag keine Mails am Wochenende'])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => CoachNote::where('user_id', $this->anna->id)->count()));
        $this->actingAs($this->lea)->get($this->url('?r=notizen'))->assertOk()->assertSee('Mag keine Mails am Wochenende');
        $id = $this->in(fn () => CoachNote::first()->id);
        $this->actingAs($this->lea)->delete($this->url("/notiz/{$id}"))->assertRedirect();
        $this->assertSame(0, $this->in(fn () => CoachNote::count()));

        $this->actingAs($this->lea)->post($this->url('/aufgabe'), ['title' => 'Werte-Liste schreiben', 'due_at' => now()->addDays(3)->toDateString()])->assertRedirect();
        $t = $this->in(fn () => Task::where('user_id', $this->anna->id)->first());
        $this->assertSame($this->lea->id, $t->assigned_by);
        $this->assertSame('coach', $t->visibility);
        $this->actingAs($this->lea)->get($this->url('?r=aufgaben'))->assertOk()->assertSee('Werte-Liste schreiben')->assertSee('von dir');

        // Termin in Ortszeit: 10:00 Zuerich im Sommer = 08:00 UTC
        $this->actingAs($this->lea)->post($this->url('/termin'), ['start' => '2026-07-01T10:00', 'dauer' => 45])->assertRedirect($this->url('?r=termine'));
        $e = $this->in(fn () => Event::where('user_id', $this->anna->id)->first());
        $this->assertSame('one_on_one', $e->type);
        $this->assertSame('2026-07-01 08:00:00', $e->getRawOriginal('starts_at'));
        $this->assertSame('2026-07-01 10:00', $this->in(fn () => Event::first()->starts_at->format('Y-m-d H:i')));

        $this->travelTo('2026-06-01 12:00:00');
        $this->actingAs($this->lea)->post($this->url('/vorschlag'), ['zeiten' => ['2026-06-03T14:00', '2026-06-04T09:30', ''], 'dauer' => 60, 'text' => 'Passt dir eine?'])->assertRedirect($this->url());
        $v = $this->in(fn () => Message::where('user_id', $this->lea->id)->latest('id')->first());
        $this->assertSame('Passt dir eine?', $v->body);
        $this->assertSame(['2026-06-03T12:00:00+00:00', '2026-06-04T07:30:00+00:00'], $v->meta['vorschlaege']);
    }

    public function test_etwas_verkaufen_schaltet_angebot_und_programme_frei(): void
    {
        Mail::fake();
        [$offer, $p] = $this->in(function () {
            $p = Program::create(['slug' => 'eins', 'title' => 'Einzelbegleitung', 'type' => 'one_on_one', 'is_published' => true, 'settings' => ['sitzungen_gesamt' => 5]]);
            $offer = Offer::create(['title' => 'Paket Klarheit', 'access_days' => 90, 'is_active' => true]);
            $offer->programs()->attach($p, ['tenant_id' => $this->a->id]);

            return [$offer, $p];
        });
        $this->actingAs($this->lea)->get($this->url())->assertOk()->assertSee('Paket Klarheit');
        $this->actingAs($this->lea)->post($this->url('/zugang'), ['offer_id' => $offer->id, 'preis' => '1200 CHF', 'sitzungen' => 2, 'notiz' => 'Zahlt in zwei Raten', 'mail' => 1])
            ->assertRedirect($this->url('?r=kurs'));
        $this->in(function () use ($offer, $p) {
            $e = Entitlement::where('user_id', $this->anna->id)->first();
            $this->assertSame($offer->id, $e->offer_id);
            $this->assertSame('manual', $e->source);
            $this->assertTrue($e->isCurrent());
            $this->assertEqualsWithDelta(now()->addDays(90)->getTimestamp(), $e->ends_at->getTimestamp(), 5);
            $pm = ProgramMember::where('user_id', $this->anna->id)->where('program_id', $p->id)->first();
            $this->assertSame(2, $pm->settings['sitzungen_extra']);
            $n = CoachNote::where('user_id', $this->anna->id)->first();
            $this->assertStringContainsString('Verkauft: Paket Klarheit', $n->body);
            $this->assertStringContainsString('1200 CHF', $n->body);
            $this->assertStringContainsString('Zahlt in zwei Raten', $n->body);
        });
        Mail::assertSent(WillkommenMail::class, fn ($m) => $m->hasTo('anna@example.com') || true);
        $this->actingAs($this->lea)->get($this->url())->assertOk()->assertSee('Paket Klarheit')->assertSee('7 Sitzungen offen');
        $this->actingAs($this->lea)->get($this->url('?r=kurs'))->assertOk()->assertSee('Einzelbegleitung');
    }

    public function test_neue_person_anlegen_aus_der_liste(): void
    {
        Mail::fake();
        $this->actingAs($this->lea)->post('http://a.test/coachees/anlegen', ['vorname' => 'Nora', 'nachname' => 'Neu', 'email' => 'Nora@Test.ch', 'phone' => '079 999 88 77', 'rolle' => 'client', 'notiz' => 'Kam über Instagram', 'mail' => 1])
            ->assertRedirect();
        $nora = User::where('email', 'nora@test.ch')->first();
        $this->assertSame('Nora Neu', $nora->name);
        $this->assertSame('079 999 88 77', $nora->phone);
        $this->in(function () use ($nora) {
            $m = Membership::where('user_id', $nora->id)->first();
            $this->assertSame(Role::Client, $m->role);
            $this->assertSame('Kam über Instagram', CoachNote::where('user_id', $nora->id)->first()->body);
        });
        Mail::assertSent(WillkommenMail::class);

        // Zweites Mal dieselbe Adresse: kein Duplikat
        $this->actingAs($this->lea)->post('http://a.test/coachees/anlegen', ['vorname' => 'Nora', 'email' => 'nora@test.ch'])->assertRedirect();
        $this->assertSame(1, User::where('email', 'nora@test.ch')->count());
        $this->actingAs($this->anna)->post('http://a.test/coachees/anlegen', ['vorname' => 'X', 'email' => 'x@test.ch'])->assertForbidden();
    }
}

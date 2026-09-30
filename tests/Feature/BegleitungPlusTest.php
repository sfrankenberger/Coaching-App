<?php

namespace Tests\Feature;

use App\Booking\Buchung;
use App\Booking\Verfuegbarkeit;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Ics;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Block C: feste Buchung mit Kalenderdatei, Herkunft und Mitgegeben, Kalender-Feed je Kurs, Aufgaben in der Terminliste, Gaeste. */
class BegleitungPlusTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected Program $kurs;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich', 'settings' => ['coach_name' => 'Lea', 'termine' => ['einzel_titel' => 'Einzelsitzung']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active']);
        $this->kurs = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'type' => 'hybrid', 'pacing' => 'weekly']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_feste_buchung_aus_dem_dossier_mit_kalenderdatei_und_doppelbelegung(): void
    {
        $m = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first());
        $start = now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i');
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$m->id}/termin", ['start' => $start, 'dauer' => 60])->assertRedirect();

        $b = $this->in(fn () => Booking::first());
        $this->assertNotNull($b);
        $this->assertSame($this->lea->id, $b->booked_by);
        $this->assertSame('dossier', $b->herkunft);
        $this->assertSame('Einzelsitzung', $b->event->title);
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $n->nachricht->titel === 'Dein Termin: Einzelsitzung'
            && in_array('mail', $channels, true) && str_contains($n->nachricht->anhang['inhalt'] ?? '', 'BEGIN:VEVENT') && str_contains($n->nachricht->anhang['inhalt'], 'TRIGGER:-PT15M'));

        // Dieselbe Zeit noch einmal: belegt
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$m->id}/termin", ['start' => $start, 'dauer' => 30])->assertStatus(409);
        // Nachtragen in der Vergangenheit geht fuers Team, ohne Bestaetigung
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$m->id}/termin", ['start' => now()->subDays(2)->format('Y-m-d\TH:i'), 'dauer' => 60])->assertRedirect();
        $this->assertSame(2, $this->in(fn () => Booking::count()));

        // Im Dossier steht die Buchung; Absage bleibt im Feed als abgesagt
        $this->actingAs($this->lea)->get("http://a.test/coachees/{$m->id}?r=termine")->assertOk()->assertSee('Einzelsitzung');
        $this->in(fn () => app(Buchung::class)->absagen($b, $this->lea));
        $this->assertNotNull($b->event->fresh()->cancelled_at);
        $token = $this->in(fn () => Ics::tokenFor($m));
        $this->get("http://a.test/kalender/{$token}.ics")->assertOk()->assertSee('STATUS:CANCELLED')->assertSee('Einzelsitzung');
    }

    public function test_kalender_feed_je_kurs_google_und_outlook_links(): void
    {
        $m = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first());
        [$call, $tag] = $this->in(fn () => [
            Event::create(['program_id' => $this->kurs->id, 'title' => 'Gruppencall', 'starts_at' => now()->addDays(2)->setTime(19, 0), 'ends_at' => now()->addDays(2)->setTime(20, 0)]),
            Event::create(['program_id' => $this->kurs->id, 'title' => 'Reflexionstag', 'type' => 'reflection_day', 'all_day' => true, 'starts_at' => now()->addDays(4)]),
        ]);
        $this->in(fn () => Event::create(['user_id' => $this->anna->id, 'title' => 'Einzelsitzung', 'type' => 'one_on_one', 'starts_at' => now()->addDays(5)->setTime(9, 0)]));
        $token = $this->in(fn () => Ics::tokenFor($m));
        $this->get("http://a.test/kalender/{$token}.ics")->assertOk()->assertSee('Gruppencall')->assertSee('Einzelsitzung')->assertSee('TRANSP:TRANSPARENT');
        $this->get("http://a.test/kalender/{$token}/hybrid.ics")->assertOk()->assertSee('Gruppencall')->assertDontSee('Einzelsitzung')->assertSee('X-WR-CALNAME:A · Hybrid', false);

        $this->actingAs($this->anna)->get("http://a.test/termine/{$call->id}")->assertOk()->assertSee('calendar.google.com/calendar/render')->assertSee('outlook.live.com');
        $this->actingAs($this->anna)->get('http://a.test/kurse/hybrid')->assertOk()->assertSee('Termine dieses Kurses abonnieren');
    }

    public function test_terminliste_mit_aufgaben_filter_und_suche(): void
    {
        $this->in(function () {
            Event::create(['program_id' => $this->kurs->id, 'title' => 'Gruppencall Atem', 'starts_at' => now()->addDays(2)->setTime(19, 0)]);
            Task::create(['user_id' => $this->anna->id, 'title' => 'Werte aufschreiben', 'due_at' => now()->addDays(3)]);
        });
        $this->actingAs($this->anna)->get('http://a.test/termine')->assertOk()->assertSee('Gruppencall Atem')->assertSee('Werte aufschreiben');
        $this->actingAs($this->anna)->get('http://a.test/termine?was=termine')->assertOk()->assertSee('Gruppencall Atem')->assertDontSee('Werte aufschreiben');
        $this->actingAs($this->anna)->get('http://a.test/termine?was=aufgaben')->assertOk()->assertDontSee('Gruppencall Atem')->assertSee('Werte aufschreiben');
        $this->actingAs($this->anna)->get('http://a.test/termine?q=Werte')->assertOk()->assertDontSee('Gruppencall Atem')->assertSee('Werte aufschreiben');
    }

    public function test_gast_erscheint_in_der_coachees_liste_mit_herkunft(): void
    {
        $gast = User::factory()->create(['name' => 'Gerda Gast']);
        $this->a->users()->attach($gast, ['role' => Role::Guest->value, 'status' => 'active']);
        $gm = $this->in(fn () => Membership::where('user_id', $gast->id)->first());
        $gm->forceFill(['settings' => ['herkunft' => 'instagram']])->save();

        $this->actingAs($this->lea)->get('http://a.test/coachees?q=gerda')->assertOk()->assertSee('Gerda Gast');
        $this->actingAs($this->lea)->get("http://a.test/coachees/{$gm->id}")->assertOk()->assertSee('kam über instagram');
    }

    public function test_buchung_mit_mitgegebenem_und_herkunft(): void
    {
        $note = $this->in(fn () => Note::create(['user_id' => $this->anna->id, 'body' => 'Darum geht es mir']));
        $art = $this->in(fn () => BookingType::create(['key' => 'klar', 'title' => 'Klarheitsgespräch', 'is_open' => true, 'duration' => 30, 'block_minutes' => 30, 'is_active' => true]));
        $start = Carbon::now()->addDays(2)->setTime(9, 0);
        // Ohne Google-Kalender direkt ueber den Dienst, wie es die Kasse und das Gastformular tun
        $this->in(function () use ($art, $start, $note) {
            $this->partialMock(Verfuegbarkeit::class, fn ($m) => $m->shouldReceive('zeiten')->andReturn(collect([$start])));
            app(Buchung::class)->buchen($this->anna, $art, $start, [['frage' => 'Worum geht es?', 'antwort' => 'Um meinen Job.']], true, 'newsletter', ['note:'.$note->id]);
        });
        $b = $this->in(fn () => Booking::with('anhaenge')->first());
        $this->assertSame('newsletter', $b->herkunft);
        $this->assertSame(['note:'.$note->id], $b->anhangRefs());
        Notification::assertSentTo($this->lea, AppNotification::class, fn (AppNotification $n) => str_contains($n->nachricht->text, 'Kam über: newsletter') && str_contains($n->nachricht->text, 'Mitgegeben: 1'));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => str_starts_with($n->nachricht->titel, 'Gebucht:') && ! empty($n->nachricht->anhang));

        $m = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first());
        $this->actingAs($this->lea)->get("http://a.test/coachees/{$m->id}?r=termine")->assertOk()->assertSee('Vorab mitgegeben')->assertSee('Um meinen Job.')->assertSee('kam über newsletter');
    }
}

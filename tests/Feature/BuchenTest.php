<?php

namespace Tests\Feature;

use App\Booking\Verfuegbarkeit;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BuchenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-09-25 08:00:00', 'UTC'));   // Freitag
        $schluessel = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($schluessel, $pem);
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich', 'settings' => [
            'google' => ['service_account' => ['client_email' => 'svc@test.iam', 'private_key' => $pem]],
            'booking' => ['enabled' => true, 'calendar_id' => 'kalender@test', 'block_keyword' => 'Coachingblock', 'lead_hours' => 4, 'horizon_days' => 30, 'grid_minutes' => 30, 'cancel_hours' => 2, 'zoom_url' => 'https://zoom.us/j/5551234567'],
        ]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active']);

        // Montag 28.09.: Block 09:00-11:00 Zuerich (07:00-09:00 UTC), dazwischen belegt 10:00-10:30
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'g-tok']),
            'www.googleapis.com/calendar/v3/calendars/*/events?*' => Http::response(['items' => [
                ['id' => 'b1', 'summary' => 'Coachingblock', 'start' => ['dateTime' => '2026-09-28T07:00:00Z'], 'end' => ['dateTime' => '2026-09-28T09:00:00Z']],
                ['id' => 'x1', 'summary' => 'Zahnarzt', 'start' => ['dateTime' => '2026-09-28T08:00:00Z'], 'end' => ['dateTime' => '2026-09-28T08:30:00Z']],
                ['id' => 'b2', 'summary' => 'Coachingblock Erst', 'start' => ['dateTime' => '2026-09-29T07:00:00Z'], 'end' => ['dateTime' => '2026-09-29T08:00:00Z']],
                ['id' => 'f1', 'summary' => 'Frei markiert', 'transparency' => 'transparent', 'start' => ['dateTime' => '2026-09-28T07:00:00Z'], 'end' => ['dateTime' => '2026-09-28T09:00:00Z']],
            ]]),
            'www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'neu-123']),
            'www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response('', 204),
        ]);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    protected function arten(): array
    {
        return $this->in(function () {
            $p = Program::create(['slug' => 'einzel', 'title' => 'Einzel', 'type' => 'one_on_one', 'settings' => ['sitzungen_gesamt' => 3]]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);

            return [
                BookingType::create(['key' => 'coaching', 'title' => '1:1 Coaching', 'event_title' => '1:1 Sitzung', 'duration' => 60, 'block_minutes' => 60, 'questions' => ['Woran willst du arbeiten?']]),
                BookingType::create(['key' => 'erst', 'title' => 'Klarheitsgespräch', 'duration' => 30, 'is_open' => true, 'block_tag' => 'Erst']),
            ];
        });
    }

    public function test_freie_zeiten_aus_bloecken(): void
    {
        [$coaching, $erst] = $this->arten();
        $zeiten = $this->in(fn () => app(Verfuegbarkeit::class)->zeiten($coaching)->map->format('d.m. H:i')->all());
        // 09:00-10:00 passt (endet genau am Zahnarzt), 09:30 und 10:00 kollidieren, 10:30-11:30 ragt hinaus; der Erst-Block gilt nicht fuer 1:1
        $this->assertSame(['28.09. 09:00'], $zeiten);
        $erstZeiten = $this->in(fn () => app(Verfuegbarkeit::class)->zeiten($erst)->map->format('d.m. H:i')->all());
        // 30 Minuten: 09:30 endet genau, wenn der Zahnarzt beginnt; der Erst-Block am 29. gilt nur fuer diese Art
        $this->assertSame(['28.09. 09:00', '28.09. 09:30', '28.09. 10:30', '29.09. 09:00', '29.09. 09:30'], $erstZeiten);
    }

    public function test_buchen_und_absagen(): void
    {
        [$coaching] = $this->arten();
        $this->actingAs($this->anna)->get('http://a.test/buchen')->assertOk()->assertSee('1:1 Coaching')->assertSee('von 3 noch offen');
        $this->actingAs($this->anna)->get('http://a.test/buchen/coaching')->assertOk()->assertSee('Montag, 28. September')->assertSee('09:00')->assertSee('Woran willst du arbeiten?');

        $start = Carbon::parse('2026-09-28 07:00:00', 'UTC')->getTimestamp();
        $r = $this->actingAs($this->anna)->post('http://a.test/buchen/coaching', ['start' => $start, 'antworten' => ['Mein Thema: Grenzen']]);
        $b = $this->in(fn () => Booking::first());
        $r->assertRedirect("http://a.test/termine/{$b->event_id}");
        $this->assertSame('neu-123', $b->google_event_id);
        $this->assertSame([['frage' => 'Woran willst du arbeiten?', 'antwort' => 'Mein Thema: Grenzen']], $b->answers);
        $this->assertSame('2026-09-28 07:00:00', DB::table('events')->value('starts_at'));
        $this->assertSame('https://zoom.us/j/5551234567', DB::table('events')->value('zoom_url'));
        Notification::assertSentTo($this->anna, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Gebucht'));
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Neue Buchung'));
        Notification::assertNotSentTo($this->anna, AppNotification::class, fn ($n) => str_starts_with($n->nachricht->titel, 'Neuer Termin'));

        // Dieselbe Zeit ist jetzt weg
        $this->actingAs($this->anna)->post('http://a.test/buchen/coaching', ['start' => $start])->assertStatus(409);
        $this->actingAs($this->lea)->get('http://a.test/coach/memberships/'.$this->in(fn () => Membership::where('user_id', $this->anna->id)->value('id')).'/dossier')
            ->assertOk()->assertSee('Mein Thema: Grenzen');

        $this->actingAs($this->anna)->post("http://a.test/buchungen/{$b->id}/absagen")->assertRedirect('http://a.test/buchen');
        $this->assertSame('abgesagt', $b->fresh()->status);
        $this->assertFalse((bool) DB::table('events')->value('is_published'));
        Http::assertSent(fn ($req) => $req->method() === 'DELETE' && str_contains($req->url(), '/events/neu-123'));
    }

    public function test_ohne_kontingent_kein_1_zu_1_und_offene_art_nur_einmal(): void
    {
        $this->in(function () {
            BookingType::create(['key' => 'coaching', 'title' => '1:1 Coaching', 'duration' => 60]);
            BookingType::create(['key' => 'erst', 'title' => 'Klarheitsgespräch', 'duration' => 30, 'is_open' => true]);
        });
        $this->actingAs($this->anna)->post('http://a.test/buchen/coaching', ['start' => Carbon::parse('2026-09-28 07:00:00', 'UTC')->getTimestamp()])->assertForbidden();
        $this->actingAs($this->anna)->post('http://a.test/buchen/erst', ['start' => Carbon::parse('2026-09-28 07:00:00', 'UTC')->getTimestamp()])->assertRedirect();
        $this->actingAs($this->anna)->post('http://a.test/buchen/erst', ['start' => Carbon::parse('2026-09-28 08:30:00', 'UTC')->getTimestamp()])->assertForbidden();
    }

    public function test_ausgeschaltet_gibt_es_keine_buchung(): void
    {
        $this->arten();
        $s = $this->a->settings;
        $s['booking']['enabled'] = false;
        $this->a->forceFill(['settings' => $s])->save();
        $this->actingAs($this->anna)->get('http://a.test/buchen')->assertNotFound();
        $this->actingAs($this->anna)->post('http://a.test/buchen/erst', ['start' => 1])->assertNotFound();
    }

    public function test_buchungen_bleiben_im_mandanten(): void
    {
        $this->arten();
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->assertSame(2, $this->in(fn () => BookingType::count()));
        $this->assertSame(0, app(CurrentTenant::class)->run($b, fn () => BookingType::count()));
        $this->in(fn () => Booking::create(['user_id' => $this->anna->id, 'starts_at' => now()->addDay()]));
        $this->assertSame(0, app(CurrentTenant::class)->run($b, fn () => Booking::count()));
    }
}

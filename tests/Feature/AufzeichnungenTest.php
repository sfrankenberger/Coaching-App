<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\WebPushChannel;
use App\Recordings\Freigabe;
use App\Recordings\Vimeo;
use App\Recordings\Wache;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AufzeichnungenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected Program $kurs;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['ai.anthropic_key' => 'sk-test', 'ai.model' => 'claude-test']);
        $this->travelTo(Carbon::parse('2026-09-21 20:30:00', 'UTC'));   // Montag 22:30 Zuerich
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich', 'settings' => ['vimeo' => ['token' => 'vt']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->kurs = $this->in(function () {
            $k = Program::create(['slug' => 'kurs', 'title' => 'Kurs']);
            ProgramMember::create(['program_id' => $k->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $k->id, 'user_id' => $this->bea->id]);

            return $k;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_vtt_wird_text_mit_zeitmarken(): void
    {
        $vtt = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHallo zusammen.\n\n2\n00:02:05.000 --> 00:02:09.000\nJetzt die Übung.\n";
        $this->assertSame("[00:01] Hallo zusammen. \n[02:05] Jetzt die Übung.", Vimeo::vttZuText($vtt));
    }

    public function test_wache_findet_video_holt_abschrift_und_zusammenfassung_und_meldet_der_coachin(): void
    {
        // Call Montag 20:00 bis 21:00 Zuerich = 18:00 bis 19:00 UTC, Abgleich um 22:30
        $call = $this->in(fn () => Event::withoutEvents(fn () => Event::create(['tenant_id' => $this->a->id, 'program_id' => $this->kurs->id, 'title' => 'Gruppencall', 'type' => 'group_call',
            'starts_at' => '2026-09-21 18:00:00', 'ends_at' => '2026-09-21 19:00:00', 'is_published' => true])));
        Http::fake([
            'api.vimeo.com/me/videos*' => Http::response(['data' => [
                ['uri' => '/videos/111', 'name' => 'Schritt 7: Feiern', 'link' => 'https://vimeo.com/111', 'duration' => 60, 'created_time' => '2026-09-21T19:30:00+00:00'],
                ['uri' => '/videos/222', 'name' => 'Persönlicher Meetingraum von Lea 2026-09-21 18:01:28', 'link' => 'https://vimeo.com/222', 'duration' => 3725, 'created_time' => '2026-09-21T19:40:00+00:00',
                    'pictures' => ['sizes' => [['width' => 320, 'link' => 'klein.jpg'], ['width' => 640, 'link' => 'gross.jpg']]]],
            ]]),
            'api.vimeo.com/videos/222/texttracks' => Http::response(['data' => [['language' => 'de', 'link' => 'https://captions.vimeo.test/222.vtt']]]),
            'captions.vimeo.test/*' => Http::response("WEBVTT\n\n00:00:05.000 --> 00:00:08.000\nWillkommen im Call.\n"),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'zusammenfassung_html' => '<p><strong>Worum es ging:</strong> Ankommen.</p><h3>Start (ab 00:05)</h3><p>Begrüssung.</p>', 'aufgaben' => [],
            ])]], 'model' => 'claude-test', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]),
        ]);

        $b = $this->in(fn () => app(Wache::class)->lauf());

        $this->assertSame(1, $b['zugeordnet']);
        $this->assertSame(1, $b['abschriften']);
        $this->assertSame(1, $b['zusammenfassungen']);
        $call->refresh();
        $this->assertSame('222', $call->vimeo_id);
        $this->assertSame('https://vimeo.com/222', $call->recording_url);
        $this->assertSame('gross.jpg', $call->recording_thumb);
        $this->assertSame('1 h 02 min', $call->recording_duration);
        $this->assertStringContainsString('[00:05] Willkommen im Call.', $call->transcript);
        $this->assertStringContainsString('(ab 00:05)', $call->summary);
        $this->assertSame('bereit', $call->recording_status);
        $this->assertNull($call->recording_notified_at, 'Freigabe bleibt bei der Coachin');
        Notification::assertSentTo($this->lea, AppNotification::class, fn (AppNotification $n) => str_starts_with($n->nachricht->titel, 'Aufzeichnung bereit'));
        Notification::assertNotSentTo($this->anna, AppNotification::class);

        // Zweiter Lauf: nichts mehr zu tun
        $this->assertSame(0, $this->in(fn () => app(Wache::class)->lauf())['gemeldet']);
    }

    public function test_nicht_gefunden_nach_allen_versuchen(): void
    {
        $call = $this->in(fn () => Event::withoutEvents(fn () => Event::create(['tenant_id' => $this->a->id, 'program_id' => $this->kurs->id, 'title' => 'Call', 'type' => 'group_call',
            'starts_at' => '2026-09-21 18:00:00', 'ends_at' => '2026-09-21 19:00:00', 'is_published' => true, 'recording_tries' => 23])));
        Http::fake(['api.vimeo.com/me/videos*' => Http::response(['data' => []])]);

        $this->in(fn () => app(Wache::class)->lauf());

        $this->assertSame('nicht_gefunden', $call->fresh()->recording_status);
        Notification::assertSentTo($this->lea, AppNotification::class, fn (AppNotification $n) => str_starts_with($n->nachricht->titel, 'Keine Aufzeichnung gefunden'));
    }

    public function test_freigabe_an_alle_im_kurs_mit_mail_und_zusammenfassung(): void
    {
        $call = $this->in(function () {
            $e = Event::withoutEvents(fn () => Event::create(['tenant_id' => $this->a->id, 'program_id' => $this->kurs->id, 'title' => 'Call', 'type' => 'group_call',
                'starts_at' => '2026-09-21 18:00:00', 'is_published' => true, 'recording_url' => 'https://vimeo.com/1', 'summary' => '<p>Worum es ging</p>']));
            EventAttendee::create(['event_id' => $e->id, 'user_id' => $this->bea->id, 'status' => 'declined']);
            PushSubscription::create(['user_id' => $this->anna->id, 'endpoint' => 'https://push.example/1', 'endpoint_hash' => hash('sha256', 'https://push.example/1')]);

            return $e;
        });

        $n = $this->in(fn () => app(Freigabe::class)->freigeben($call, ['mail', 'push']));

        $this->assertSame(2, $n, 'auch wer abgesagt hat, bekommt die Aufzeichnung');
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $x, $ch) => $x->nachricht->anlass === 'aufzeichnung' && in_array(WebPushChannel::class, $ch, true) && in_array('mail', $ch, true) && $x->nachricht->html === '<p>Worum es ging</p>');
        Notification::assertSentTo($this->bea, AppNotification::class, fn (AppNotification $x, $ch) => $ch === ['mail']);
        $this->assertNotNull($call->fresh()->recording_notified_at);
        $this->assertSame('freigegeben', $call->fresh()->recording_status);

        $this->expectException(HttpException::class);
        $this->in(fn () => app(Freigabe::class)->freigeben($call->fresh(), ['mail']));
    }

    public function test_neuer_termin_wird_gemeldet(): void
    {
        $this->in(fn () => Event::create(['program_id' => $this->kurs->id, 'title' => 'Zusatzcall', 'type' => 'group_call', 'starts_at' => now()->addDays(3), 'is_published' => true]));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Neuer Termin: Zusatzcall');
        Notification::assertSentTo($this->bea, AppNotification::class);

        // Vergangene, Entwuerfe und Reflexionstage nicht
        $this->in(fn () => Event::create(['program_id' => $this->kurs->id, 'title' => 'Alt', 'type' => 'group_call', 'starts_at' => now()->subDay(), 'is_published' => true]));
        $this->in(fn () => Event::create(['program_id' => $this->kurs->id, 'title' => 'Entwurf', 'type' => 'group_call', 'starts_at' => now()->addDay(), 'is_published' => false]));
        $this->in(fn () => Event::create(['program_id' => $this->kurs->id, 'title' => 'Reflexion', 'type' => 'reflection_day', 'starts_at' => now()->addDay(), 'is_published' => true]));
        Notification::assertSentToTimes($this->anna, AppNotification::class, 1);
    }
}

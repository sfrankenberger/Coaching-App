<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Enums\Role;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TelegramLink;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Runden;
use App\Notifications\TelegramChannel;
use App\Notifications\WebPushChannel;
use App\Programs\Begleitung;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BenachrichtigungenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich', 'settings' => ['mail' => ['from_address' => 'coach@example.com', 'from_name' => 'Coach']]]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['notifications' => ['termine' => false, 'abendmail' => false, 'aufgaben' => true]]]);
        $this->program = $this->in(function () {
            $p = Program::create(['slug' => 'kurs', 'title' => 'Kurs A']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->bea->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_nachricht_geht_per_mail_ohne_push_und_per_push_mit_abo(): void
    {
        Notification::fake();
        $this->in(function () {
            $conv = app(Chat::class)->directFor($this->anna);
            app(Chat::class)->send($conv, $this->lea, ['body' => 'Hallo Anna']);
        });
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $channels === ['mail'] && $n->nachricht->titel === 'Lea hat dir geschrieben' && $n->tenantId === $this->a->id);
        Notification::assertNotSentTo($this->lea, AppNotification::class);

        $this->in(fn () => PushSubscription::create(['user_id' => $this->anna->id, 'endpoint' => 'https://push.example/1', 'endpoint_hash' => hash('sha256', 'https://push.example/1'), 'p256dh' => 'x', 'auth' => 'y']));
        $this->in(fn () => app(Chat::class)->send(app(Chat::class)->directFor($this->anna), $this->lea, ['body' => 'Nochmal']));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $n->nachricht->text === 'Nochmal' && $channels === [WebPushChannel::class]);

        $this->in(fn () => TelegramLink::create(['user_id' => $this->anna->id, 'chat_id' => '42', 'active' => true]));
        $this->in(fn () => app(Chat::class)->send(app(Chat::class)->directFor($this->anna), $this->lea, ['body' => 'Dritte']));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $n->nachricht->text === 'Dritte' && $channels === [WebPushChannel::class, TelegramChannel::class]);
    }

    public function test_mail_inhalt_und_absender(): void
    {
        $this->in(function () {
            $conv = app(Chat::class)->directFor($this->anna);
            app(Chat::class)->send($conv, $this->lea, ['body' => 'Hallo Anna, wie geht es dir?']);
        });
        // Ohne Notification::fake landet die Mail im Array-Mailer
        $mail = app('mailer')->getSymfonyTransport()->messages()->last()?->getOriginalMessage();
        $this->assertNotNull($mail);
        $this->assertSame('coach@example.com', $mail->getFrom()[0]->getAddress());
        $this->assertStringContainsString('Lea hat dir geschrieben', $mail->getSubject());
        $this->assertStringContainsString('Hallo Anna, wie geht es dir?', $mail->getHtmlBody());
    }

    public function test_termin_erinnerung_tag_und_stunde_ohne_doppelte(): void
    {
        Notification::fake();
        $this->travelTo(now('Europe/Zurich')->setTime(10, 0));
        $event = $this->in(fn () => Event::create(['program_id' => $this->program->id, 'title' => 'Call', 'starts_at' => now()->addMinutes(50), 'zoom_url' => 'https://zoom.us/j/1']));
        $this->in(fn () => EventAttendee::create(['event_id' => $event->id, 'user_id' => $this->anna->id, 'status' => 'invited']));

        $n = $this->in(fn () => app(Runden::class)->terminErinnerungen());
        // Tag (ab 9 Uhr) und Stunde (in 50 Min) je an Anna; Bea hat Termin-Erinnerungen aus
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => str_starts_with($n->nachricht->titel, 'Heute'));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => str_starts_with($n->nachricht->titel, 'In einer Stunde'));
        Notification::assertNotSentTo($this->bea, AppNotification::class);
        $this->assertSame(2, $n);

        $this->assertSame(0, $this->in(fn () => app(Runden::class)->terminErinnerungen()), 'kein zweites Mal');
        $this->assertNotNull($event->fresh()->reminded_hour_at);
    }

    public function test_aufgabe_von_coach_und_material_geteilt(): void
    {
        Notification::fake();
        $this->in(fn () => Task::create(['user_id' => $this->anna->id, 'assigned_by' => $this->lea->id, 'title' => 'Intention schreiben', 'source' => 'coach']));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Lea hat dir eine Aufgabe gegeben');

        $this->in(fn () => Task::create(['user_id' => $this->anna->id, 'title' => 'Eigene']));
        Notification::assertSentToTimes($this->anna, AppNotification::class, 1);

        $this->in(function () {
            $r = Resource::create(['title' => 'Arbeitsblatt', 'url' => 'https://example.com/a.pdf']);
            app(Begleitung::class)->share($r, $this->anna->id, $this->lea->id);
        });
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->anlass === 'material');
    }

    public function test_abendmail_nur_ohne_push_und_nur_bei_neuem(): void
    {
        Notification::fake();
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->abendmail()), 'nichts Neues');

        $this->in(fn () => Event::create(['program_id' => $this->program->id, 'title' => 'Call', 'starts_at' => now()->subDay(), 'recording_url' => 'https://vimeo.com/1']));
        $this->assertSame(1, $this->in(fn () => app(Runden::class)->abendmail()), 'Anna ja, Bea hat Abendmail aus');
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, $channels) => $n->nachricht->anlass === 'abendmail' && $channels === ['mail'] && str_contains($n->nachricht->text, 'Aufzeichnung: Call'));
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->abendmail()), 'nicht zweimal am Tag');
    }

    public function test_nachfassen_bei_ungelesener_nachricht(): void
    {
        Notification::fake();
        $this->in(fn () => app(Chat::class)->send(app(Chat::class)->directFor($this->anna), $this->lea, ['body' => 'Liest du das?']));
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->nachfassen()), 'noch keine 20 Minuten');
        $this->travel(25)->minutes();
        $this->assertSame(1, $this->in(fn () => app(Runden::class)->nachfassen()));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, $channels) => $channels === ['mail'] && $n->nachricht->text === 'Liest du das?');
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->nachfassen()), 'nur einmal');
    }

    public function test_push_abo_und_schluessel(): void
    {
        $this->actingAs($this->anna)->getJson('http://a.test/push/schluessel')->assertOk()->assertJsonStructure(['publicKey']);
        $this->assertNotNull($this->a->fresh()->setting('push.vapid.private'));

        $this->actingAs($this->anna)->postJson('http://a.test/push/abo', ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk()->assertJsonPath('anzahl', 1);
        $this->assertSame(1, $this->in(fn () => PushSubscription::where('user_id', $this->anna->id)->count()));
        $this->actingAs($this->anna)->deleteJson('http://a.test/push/abo', ['endpoint' => 'https://push.example/abc'])->assertOk()->assertJsonPath('anzahl', 0);
    }

    public function test_telegram_verbinden_und_antworten(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]])]);
        $this->a->update(['settings' => array_merge($this->a->settings, ['telegram' => ['bot_token' => 'T', 'bot_username' => 'bot', 'webhook_secret' => 'geheim']])]);

        $this->actingAs($this->anna)->post('http://a.test/telegram/verbinden')->assertRedirect();
        $link = $this->in(fn () => TelegramLink::where('user_id', $this->anna->id)->first());
        $this->assertFalse($link->active);
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertSee('t.me/bot?start='.$link->code);

        $this->postJson('http://a.test/hooks/telegram/falsch', ['message' => ['chat' => ['id' => 99], 'text' => '/start '.$link->code]])->assertForbidden();
        $this->postJson('http://a.test/hooks/telegram/geheim', ['message' => ['chat' => ['id' => 99], 'from' => ['username' => 'anna'], 'text' => '/start '.$link->code]])->assertOk();
        $this->assertTrue($link->fresh()->active);
        $this->assertSame('99', $link->fresh()->chat_id);

        // Anna schreibt in Telegram: landet im 1:1
        $this->postJson('http://a.test/hooks/telegram/geheim', ['message' => ['chat' => ['id' => 99], 'text' => 'Hallo aus Telegram']])->assertOk();
        $msg = $this->in(fn () => Message::latest('id')->first());
        $this->assertSame('Hallo aus Telegram', $msg->body);
        $this->assertSame('telegram', $msg->source);
        $this->assertSame($this->anna->id, $msg->user_id);
    }
}

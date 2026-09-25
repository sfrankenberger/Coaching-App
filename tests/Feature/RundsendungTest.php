<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Rundsendung;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RundsendungTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_an_alle_und_an_ein_programm_mit_gruppenchat(): void
    {
        $r = $this->in(fn () => app(Rundsendung::class)->send(['an' => 'alle', 'titel' => 'Hallo zusammen', 'text' => 'Morgen geht es los.', 'kanaele' => ['push', 'mail']], $this->lea));
        $this->assertSame(2, $r['empfaenger']);
        $this->assertSame(2, $r['erreicht'], 'ohne Push per Mail');
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Hallo zusammen' && in_array('mail', $n->channels, true));
        Notification::assertNotSentTo($this->lea, AppNotification::class);

        $kurs = $this->in(function () {
            $kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            ProgramMember::create(['program_id' => $kurs->id, 'user_id' => $this->anna->id]);
            PushSubscription::create(['user_id' => $this->bea->id, 'endpoint' => 'https://push.test/x', 'endpoint_hash' => sha1('https://push.test/x'), 'p256dh' => 'a', 'auth' => 'b']);

            return $kurs;
        });
        $r = $this->in(fn () => app(Rundsendung::class)->send(['an' => 'programm', 'program_id' => $kurs->id, 'titel' => 'Nur Kurs', 'text' => 'Text', 'kanaele' => ['push'], 'chat' => true], $this->lea));
        $this->assertSame(1, $r['empfaenger']);
        $this->assertTrue($r['chat']);
        $this->assertSame(0, $r['erreicht'], 'Anna hat kein Push und Mail ist aus');
        Notification::assertSentToTimes($this->anna, AppNotification::class, 1);
        Notification::assertSentToTimes($this->bea, AppNotification::class, 1);
        $this->in(fn () => $this->assertSame(1, Message::where('user_id', $this->lea->id)->where('body', 'like', 'Nur Kurs%')->count()));
    }
}

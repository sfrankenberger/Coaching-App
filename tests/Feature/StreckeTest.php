<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Answer;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Programs\ProgressTracker;
use App\Programs\Strecke;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StreckeTest extends TestCase
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
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->kurs = $this->in(function () {
            $p = Program::create(['slug' => 'der-anfang', 'title' => 'Der Anfang', 'type' => 'selfpaced', 'pacing' => 'self_paced', 'settings' => ['gratis' => true, 'strecke' => true, 'strecke_gespraech' => 'https://example.ch/klarheit']]);
            $u1 = $p->units()->create(['title' => 'Die Schneekugel', 'position' => 1]);
            $u2 = $p->units()->create(['title' => 'Deine Goldnuggets', 'position' => 2]);
            $u2->exercises()->create(['type' => 'list', 'prompt' => 'Drei Sätze', 'position' => 1, 'legacy_key' => 'an04-u01-f1']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id, 'joined_at' => now()]);
            PushSubscription::create(['user_id' => $this->lea->id, 'endpoint' => 'https://push.example/lea', 'endpoint_hash' => hash('sha256', 'lea'), 'p256dh' => 'x', 'auth' => 'y']);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_anstoss_nach_zwei_und_sieben_tagen_dann_ruhe(): void
    {
        $this->assertSame(0, $this->in(fn () => app(Strecke::class)->lauf()), 'am ersten Tag nichts');

        $this->travel(2)->days();
        $this->assertSame(1, $this->in(fn () => app(Strecke::class)->lauf()));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $n->nachricht->titel === 'Dein Link zu «Der Anfang»'
            && $channels === ['mail'] && str_contains($n->nachricht->url, '/anmelden/') && str_contains($n->nachricht->html, '/strecke/'));
        $this->assertSame(0, $this->in(fn () => app(Strecke::class)->lauf()), 'nicht zweimal');

        // Anna schaut kurz rein und bleibt bei Schritt 2 haengen
        $this->in(fn () => app(ProgressTracker::class)->toggle($this->anna, Unit::where('title', 'Die Schneekugel')->first(), true));
        $this->travel(5)->days();
        Notification::fake();
        $this->assertSame(1, $this->in(fn () => app(Strecke::class)->lauf()));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Ein letzter Anstoss, dann lasse ich dich in Ruhe'
            && str_contains($n->nachricht->text, 'Deine Goldnuggets') && str_contains($n->nachricht->html, 'https://example.ch/klarheit'));
        $this->travel(10)->days();
        $this->assertSame(0, $this->in(fn () => app(Strecke::class)->lauf()), 'danach Ruhe');
    }

    public function test_stopplink_beendet_die_strecke(): void
    {
        $url = $this->in(fn () => app(Strecke::class)->stoppUrl($this->anna, $this->kurs));
        $this->get(str_replace('http://localhost', 'http://a.test', $url))->assertOk()->assertSee('keine Erinnerungen mehr');
        $this->get('http://a.test/strecke/'.$this->kurs->id.'/'.$this->anna->id.'/stopp')->assertForbidden();
        $this->travel(3)->days();
        $this->assertSame(0, $this->in(fn () => app(Strecke::class)->lauf()));
    }

    public function test_abschluss_schickt_goldnuggets_und_meldet_dem_team(): void
    {
        [$u1, $u2, $ex] = $this->in(fn () => [Unit::where('title', 'Die Schneekugel')->first(), Unit::where('title', 'Deine Goldnuggets')->first(), Exercise::first()]);
        $this->in(fn () => Answer::create(['user_id' => $this->anna->id, 'exercise_id' => $ex->id, 'value' => ['v' => ["Ich darf langsam sein\nNiemand wartet auf Perfektion"]]]));

        // Auf der letzten Seite steht, was gleich passiert
        $this->actingAs($this->anna)->get("http://a.test/kurse/der-anfang/einheit/{$u2->id}")->assertOk()->assertSee('Deine Goldnuggets kommen gleich per Mail')->assertSee('Ich darf langsam sein');

        $this->in(fn () => app(ProgressTracker::class)->toggle($this->anna, $u1, true));
        Notification::assertNotSentTo($this->anna, AppNotification::class);
        $this->in(fn () => app(ProgressTracker::class)->toggle($this->anna, $u2, true));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n, array $channels) => $n->nachricht->titel === 'Deine Goldnuggets'
            && in_array('mail', $channels, true) && str_contains($n->nachricht->html, 'Niemand wartet auf Perfektion'));
        Notification::assertSentTo($this->lea, AppNotification::class, fn (AppNotification $n) => str_starts_with($n->nachricht->titel, 'Jemand hat «Der Anfang» durch'));
        $this->assertNotNull($this->in(fn () => ProgramMember::first()->settings['strecke']['abschluss_at'] ?? null));

        // Nochmal abhaken schickt nichts mehr, die Strecke laeuft auch nicht mehr
        Notification::fake();
        $this->in(fn () => app(ProgressTracker::class)->toggle($this->anna, $u2, true));
        Notification::assertNothingSent();
        $this->travel(3)->days();
        $this->assertSame(0, $this->in(fn () => app(Strecke::class)->lauf()));
    }
}

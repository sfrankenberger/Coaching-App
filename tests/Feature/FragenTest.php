<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Runden;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FragenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected User $fremd;

    protected Program $kurs;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['coach_name' => 'Lea']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->fremd = User::factory()->create(['name' => 'Fremd']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->b->users()->attach($this->fremd, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->kurs = $this->in(function () {
            $p = Program::create(['slug' => 'hybrid', 'title' => 'Hybrid', 'type' => 'hybrid', 'pacing' => 'weekly']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->bea->id]);

            return $p;
        });
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_frage_stellen_beantworten_status_und_callwunsch(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Wie visualisiere ich meine Szene?', 'body' => 'Ich sehe nichts.', 'visibility' => 'program'])->assertRedirect();
        $f = $this->in(fn () => Question::first());
        Notification::assertSentTo($this->lea, AppNotification::class);

        // Bea sieht die Frage im Kurs und wuenscht sie sich fuer den Call
        $this->actingAs($this->bea)->get('http://a.test/kurse/hybrid/fragen')->assertOk()->assertSee('Wie visualisiere ich meine Szene?')->assertSee('Offen');
        $this->actingAs($this->bea)->post("http://a.test/fragen/{$f->id}/call")->assertRedirect();
        $this->actingAs($this->bea)->get('http://a.test/kurse/hybrid/fragen')->assertSee('1× für den Call gewünscht');

        // Lea antwortet: Status wird "beantwortet", Anna bekommt Bescheid
        $this->actingAs($this->lea)->post("http://a.test/fragen/{$f->id}/antworten", ['body' => 'Stell dir einen Morgen vor.'])->assertRedirect();
        $this->assertSame('beantwortet', $f->fresh()->status);
        Notification::assertSentTo($this->anna, AppNotification::class);
        $this->actingAs($this->anna)->get("http://a.test/fragen/{$f->id}")->assertOk()->assertSee('Stell dir einen Morgen vor.')->assertSee('Team');

        // Nur Verwaltende setzen den Status
        $this->actingAs($this->anna)->post("http://a.test/fragen/{$f->id}/status", ['status' => 'zu'])->assertForbidden();
        $this->actingAs($this->lea)->post("http://a.test/fragen/{$f->id}/status", ['status' => 'call'])->assertRedirect();
        $this->assertSame('call', $f->fresh()->status);
    }

    public function test_nur_fuer_die_coachin_und_mandanten_getrennt(): void
    {
        $this->actingAs($this->anna)->post('http://a.test/kurse/hybrid/fragen', ['title' => 'Private Frage', 'visibility' => 'coach']);
        $f = $this->in(fn () => Question::first());

        $this->actingAs($this->bea)->get('http://a.test/kurse/hybrid/fragen')->assertDontSee('Private Frage');
        $this->actingAs($this->bea)->get("http://a.test/fragen/{$f->id}")->assertForbidden();
        $this->actingAs($this->lea)->get("http://a.test/fragen/{$f->id}")->assertOk()->assertSee('Private Frage');

        // Mandant B sieht nichts von A
        $this->actingAs($this->fremd)->get("http://b.test/fragen/{$f->id}")->assertNotFound();
        app(CurrentTenant::class)->run($this->b, fn () => $this->assertSame(0, Question::count()));
    }

    public function test_sammelmail_am_sammeltag_an_das_team(): void
    {
        $this->in(fn () => Question::create(['program_id' => $this->kurs->id, 'user_id' => $this->anna->id, 'title' => 'Offene Frage']));
        $this->travelTo(now()->next('Thursday')->setTime(17, 0));
        $n = $this->in(fn () => app(Runden::class)->fragenSammelmail());
        $this->assertSame(1, $n);
        Notification::assertSentTo($this->lea, AppNotification::class, fn ($x) => str_contains($x->nachricht->text, 'Offene Frage'));

        $this->travelTo(now()->next('Friday'));
        $this->assertSame(0, $this->in(fn () => app(Runden::class)->fragenSammelmail()));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Answer;
use App\Models\Entitlement;
use App\Models\Exercise;
use App\Models\Note;
use App\Models\Offer;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Progress;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected CurrentTenant $cur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->cur = app(CurrentTenant::class);
    }

    protected function programIn(Tenant $t, array $attrs = []): Program
    {
        return $this->cur->run($t, fn () => Program::create(array_merge(['slug' => 'kurs-'.uniqid(), 'title' => 'Kurs'], $attrs)));
    }

    public function test_alle_neuen_tabellen_sind_nach_mandant_getrennt(): void
    {
        $user = User::factory()->create();
        $this->cur->run($this->a, function () use ($user) {
            $p = Program::create(['slug' => 'k', 'title' => 'K']);
            $s = ProgramStep::create(['program_id' => $p->id, 'title' => 'S']);
            $u = Unit::create(['program_id' => $p->id, 'step_id' => $s->id, 'title' => 'U']);
            $e = Exercise::create(['unit_id' => $u->id, 'type' => 'text', 'prompt' => 'Frage']);
            Answer::create(['user_id' => $user->id, 'exercise_id' => $e->id, 'value' => ['v' => 'x']]);
            Progress::create(['user_id' => $user->id, 'unit_id' => $u->id, 'completed_at' => now()]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $user->id]);
            $o = Offer::create(['title' => 'O']);
            $o->products()->create(['source' => 'manual', 'external_id' => '1']);
            Entitlement::create(['user_id' => $user->id, 'offer_id' => $o->id]);
            Note::create(['user_id' => $user->id, 'body' => 'n']);
        });

        foreach ([Program::class, ProgramStep::class, Unit::class, Exercise::class, Answer::class, Progress::class, ProgramMember::class, Offer::class, Entitlement::class, Note::class] as $model) {
            $this->assertSame(1, $this->cur->run($this->a, fn () => $model::count()), $model);
            $this->assertSame(0, $this->cur->run($this->b, fn () => $model::count()), $model);
            $this->assertSame(0, $model::count(), $model.' ohne Mandant');
        }
    }

    public function test_zugang_ueber_mitgliedschaft_angebot_oder_rolle(): void
    {
        $access = app(ProgramAccess::class);
        $p1 = $this->programIn($this->a);
        $p2 = $this->programIn($this->a);
        $intern = $this->programIn($this->a, ['is_internal' => true]);
        $unpublished = $this->programIn($this->a, ['is_published' => false]);

        $anna = User::factory()->create();
        $bea = User::factory()->create();
        $lea = User::factory()->create();
        $this->a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);

        $this->cur->run($this->a, function () use ($access, $p1, $p2, $intern, $unpublished, $anna, $bea, $lea) {
            ProgramMember::create(['program_id' => $p1->id, 'user_id' => $anna->id]);

            $offer = Offer::create(['title' => 'Paket']);
            $offer->programs()->attach([$p2->id => ['tenant_id' => $this->a->id], $intern->id => ['tenant_id' => $this->a->id]]);
            Entitlement::create(['user_id' => $bea->id, 'offer_id' => $offer->id, 'ends_at' => now()->addDay()]);
            Entitlement::create(['user_id' => $anna->id, 'offer_id' => $offer->id, 'ends_at' => now()->subDay()]);

            $this->assertTrue($access->canView($anna, $p1), 'direkt');
            $this->assertFalse($access->canView($anna, $p2), 'abgelaufener Zugang');
            $this->assertTrue($access->canView($bea, $p2), 'ueber Angebot');
            $this->assertFalse($access->canView($bea, $intern), 'intern nur direkt');
            $this->assertFalse($access->canView($bea, $p1));
            $this->assertTrue($access->canView($lea, $p1));
            $this->assertTrue($access->canView($lea, $intern));
            $this->assertTrue($access->canView($lea, $unpublished));

            $this->assertFalse($access->canView($anna, $unpublished));

            $this->assertSame([$p1->id], $access->programsFor($anna)->pluck('id')->all());

            // Gratiskurs: alle sehen ihn ohne Kauf, ausser er ist intern
            $gratis = Program::create(['title' => 'Gratis', 'slug' => 'gratis', 'settings' => ['gratis' => true]]);
            $gratisIntern = Program::create(['title' => 'Gratis intern', 'slug' => 'gratis-intern', 'settings' => ['gratis' => true], 'is_internal' => true]);
            $this->assertTrue($access->canView($bea, $gratis));
            $this->assertFalse($access->canView($bea, $gratisIntern));
        });
    }
}

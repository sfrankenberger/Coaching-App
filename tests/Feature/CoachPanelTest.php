<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Answer;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'branding' => ['app_name' => 'Coaching A', 'primary' => '#B4795F']]);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
    }

    protected function person(Tenant $tenant, Role $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role->value, 'status' => 'active']);

        return $user;
    }

    public function test_gast_landet_auf_der_anmeldung(): void
    {
        $this->get('http://a.test/coach')->assertRedirect('http://a.test/coach/login');
        $this->get('http://a.test/coach/login')->assertRedirect('http://a.test/anmelden?weiter=%2Fcoach');
    }

    public function test_teilnehmerin_darf_nicht_in_den_coach_bereich(): void
    {
        $this->actingAs($this->person($this->a, Role::Member))->get('http://a.test/coach')->assertForbidden();
    }

    public function test_owner_und_team_sehen_den_coach_bereich(): void
    {
        $this->actingAs($this->person($this->a, Role::Owner))->get('http://a.test/coach')->assertOk()->assertSee('Coaching A')->assertSee('Aktive Personen')->assertSee('Neu von den Personen');
        $this->actingAs($this->person($this->a, Role::Team))->get('http://a.test/coach/memberships')->assertOk();
    }

    public function test_owner_von_a_sieht_nur_personen_von_a(): void
    {
        $owner = $this->person($this->a, Role::Owner);
        $this->person($this->a, Role::Member)->update(['name' => 'Anna von A']);
        $this->person($this->b, Role::Member)->update(['name' => 'Bea von B']);

        $this->actingAs($owner)->get('http://a.test/coach/memberships')
            ->assertOk()->assertSee('Anna von A')->assertDontSee('Bea von B');

        // Owner von A ist auf b.test niemand
        $this->actingAs($owner)->get('http://b.test/coach')->assertForbidden();
    }

    public function test_plattform_nur_fuer_plattform_admin(): void
    {
        $this->actingAs($this->person($this->a, Role::Owner))->get('http://a.test/plattform')->assertForbidden();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get('http://a.test/plattform/tenants')->assertOk()->assertSee('A')->assertSee('B');
    }

    public function test_programme_und_angebote_im_coach_bereich(): void
    {
        $owner = $this->person($this->a, Role::Owner);
        $cur = app(CurrentTenant::class);
        [$program, $offer] = $cur->run($this->a, function () {
            $p = Program::create(['slug' => 'testkurs', 'title' => 'Testkurs A']);
            $s = $p->steps()->create(['title' => 'Woche 1', 'position' => 1]);
            $u = $p->units()->create(['title' => 'Einheit 1', 'step_id' => $s->id, 'position' => 1]);
            $u->exercises()->create(['type' => 'text', 'prompt' => 'Was willst du?', 'position' => 1]);
            $o = Offer::create(['title' => 'Paket A']);

            return [$p, $o];
        });
        $cur->run($this->b, fn () => Program::create(['slug' => 'kurs-b', 'title' => 'Kurs von B']));

        $this->actingAs($owner)->get('http://a.test/coach/programs')->assertOk()->assertSee('Testkurs A')->assertDontSee('Kurs von B');
        $this->actingAs($owner)->get('http://a.test/coach/programs/neu')->assertOk();
        $this->actingAs($owner)->get("http://a.test/coach/programs/{$program->id}/bearbeiten")->assertOk()->assertSee('Testkurs A');
        $this->actingAs($owner)->get('http://a.test/coach/offers')->assertOk()->assertSee('Paket A');
        $this->actingAs($owner)->get("http://a.test/coach/offers/{$offer->id}/bearbeiten")->assertOk();

        $cur->run($this->a, function () use ($program) {
            Event::create(['program_id' => $program->id, 'title' => 'Call Woche 1', 'starts_at' => now()->addDay()]);
            \App\Models\Resource::create(['title' => 'Arbeitsblatt A', 'url' => 'https://example.com/a.pdf']);
        });
        $this->actingAs($owner)->get('http://a.test/coach/events')->assertOk()->assertSee('Call Woche 1');
        $this->actingAs($owner)->get('http://a.test/coach/events/neu')->assertOk();
        $this->actingAs($owner)->get('http://a.test/coach/materials')->assertOk()->assertSee('Arbeitsblatt A');
        $this->actingAs($owner)->get('http://a.test/coach/materials/neu')->assertOk();
        $this->actingAs($owner)->get('http://a.test/coach/tasks')->assertOk();
        $this->actingAs($owner)->get('http://a.test/coach/tasks/neu')->assertOk();

        $cur->run($this->a, function () {
            Post::create(['title' => 'Impuls A', 'published_at' => now()]);
            PodcastEpisode::create(['show' => 'Sendung', 'guid' => 'g', 'title' => 'Folge A', 'audio_url' => 'https://example.com/a.mp3']);
            Topic::create(['name' => 'Thema A']);
        });
        $this->actingAs($owner)->get('http://a.test/coach/posts')->assertOk()->assertSee('Impuls A');
        $this->actingAs($owner)->get('http://a.test/coach/posts/neu')->assertOk();
        $this->actingAs($owner)->get('http://a.test/coach/podcast')->assertOk()->assertSee('Folge A');
        $this->actingAs($owner)->get('http://a.test/coach/topics')->assertOk()->assertSee('Thema A');
        $this->actingAs($owner)->get('http://a.test/coach/topics/neu')->assertOk();
        $this->actingAs($owner)->get('http://a.test/coach/rundnachricht')->assertOk()->assertSee('Rundnachricht');

        $anna = $this->person($this->a, Role::Member);
        $anna->update(['name' => 'Anna Dossier', 'phone' => '079 111 22 33']);
        $m = $cur->run($this->a, fn () => Membership::where('user_id', $anna->id)->first());
        $cur->run($this->a, function () use ($anna, $program) {
            ProgramMember::create(['program_id' => $program->id, 'user_id' => $anna->id, 'share_mode' => 'alles']);
            $ex = $program->units()->first()->exercises()->first();
            Answer::create(['user_id' => $anna->id, 'exercise_id' => $ex->id, 'value' => ['v' => 'Mehr Ruhe im Alltag'], 'shared_with_coach' => true]);
            Reflection::create(['user_id' => $anna->id, 'went_well' => 'Geteilte Reflexion', 'visibility' => 'coach', 'shared_at' => now()]);
            Reflection::create(['user_id' => $anna->id, 'went_well' => 'Private Reflexion']);
            Task::create(['user_id' => $anna->id, 'title' => 'Aufgabe von Anna']);
        });
        $this->actingAs($owner)->get("http://a.test/coach/memberships/{$m->id}/dossier")->assertOk()
            ->assertSee('Anna Dossier')->assertSee('Mehr Ruhe im Alltag')->assertSee('Geteilte Reflexion')->assertDontSee('Private Reflexion')->assertSee('Aufgabe von Anna')->assertSee('wa.me/0791112233');
    }
}

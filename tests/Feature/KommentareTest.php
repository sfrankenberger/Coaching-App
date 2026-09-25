<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Resources\Memberships\Pages\Dossier;
use App\Models\CoachNote;
use App\Models\Comment;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class KommentareTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = $this->person('Lea Coach', Role::Owner);
        $this->anna = $this->person('Anna Muster', Role::Member);
        $this->bea = $this->person('Bea Beispiel', Role::Member);
    }

    protected function person(string $name, Role $role): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->a->users()->attach($user, ['role' => $role->value, 'status' => 'active']);

        return $user;
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_coachin_antwortet_auf_geteilte_reflexion_und_person_sieht_es(): void
    {
        Notification::fake();
        [$geteilt, $privat] = $this->in(fn () => [
            Reflection::create(['user_id' => $this->anna->id, 'went_well' => 'Geteilt', 'visibility' => 'coach', 'shared_at' => now()]),
            Reflection::create(['user_id' => $this->anna->id, 'went_well' => 'Privat']),
        ]);

        $this->actingAs($this->lea)->post('http://a.test/kommentar', ['typ' => 'reflection', 'id' => $geteilt->id, 'body' => 'Schön, wie du das beschreibst'])->assertRedirect();
        $this->actingAs($this->lea)->post('http://a.test/kommentar', ['typ' => 'reflection', 'id' => $privat->id, 'body' => 'Darf nicht'])->assertForbidden();
        $this->actingAs($this->bea)->post('http://a.test/kommentar', ['typ' => 'reflection', 'id' => $geteilt->id, 'body' => 'Fremd'])->assertForbidden();
        Notification::assertSentTo($this->anna, AppNotification::class);

        $this->actingAs($this->anna)->get('http://a.test/reflexion')->assertOk()->assertSee('Schön, wie du das beschreibst');

        // Anna antwortet, das Team bekommt Bescheid
        $this->actingAs($this->anna)->post('http://a.test/kommentar', ['typ' => 'reflection', 'id' => $geteilt->id, 'body' => 'Danke dir'])->assertRedirect();
        Notification::assertSentTo($this->lea, AppNotification::class);
        $this->assertSame(2, $this->in(fn () => Comment::count()));

        // Loeschen nur eigene
        $c = $this->in(fn () => Comment::where('body', 'Danke dir')->first());
        $this->actingAs($this->bea)->delete("http://a.test/kommentar/{$c->id}")->assertForbidden();
        $this->actingAs($this->anna)->delete("http://a.test/kommentar/{$c->id}")->assertRedirect();
        $this->assertSame(1, $this->in(fn () => Comment::count()));
    }

    public function test_dossier_notizen_und_antworten(): void
    {
        Notification::fake();
        $m = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first());
        [$notiz, $aufgabe] = $this->in(fn () => [
            Note::create(['user_id' => $this->anna->id, 'body' => 'Geteilte Notiz', 'visibility' => 'coach']),
            Task::create(['user_id' => $this->anna->id, 'title' => 'Geteilte Aufgabe', 'visibility' => 'coach']),
        ]);

        $this->in(function () use ($m, $notiz, $aufgabe) {
            Filament::setCurrentPanel(Filament::getPanel('coach'));
            $this->actingAs($this->lea);
            Livewire::test(Dossier::class, ['record' => $m->id])
                ->set('notiz', 'Braucht gerade Ruhe')->call('notizSpeichern')->assertHasNoErrors()
                ->set("antwort.note-{$notiz->id}", 'Danke fürs Teilen')->call('antworten', 'note', $notiz->id)
                ->set("antwort.task-{$aufgabe->id}", 'Wie läuft es?')->call('antworten', 'task', $aufgabe->id)
                ->assertSee('Braucht gerade Ruhe')->assertSee('Danke fürs Teilen');
        });

        $this->assertSame(1, $this->in(fn () => CoachNote::where('user_id', $this->anna->id)->count()));
        $this->assertSame(2, $this->in(fn () => Comment::count()));
        $this->actingAs($this->anna)->get('http://a.test/notizen')->assertOk()->assertSee('Danke fürs Teilen')->assertDontSee('Braucht gerade Ruhe');
        $this->actingAs($this->anna)->get('http://a.test/aufgaben')->assertOk()->assertSee('Wie läuft es?');
    }

    public function test_coach_notizen_bleiben_im_mandanten(): void
    {
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $this->in(fn () => CoachNote::create(['user_id' => $this->anna->id, 'author_id' => $this->lea->id, 'body' => 'Nur in A']));

        $this->assertSame(1, $this->in(fn () => CoachNote::count()));
        $this->assertSame(0, app(CurrentTenant::class)->run($b, fn () => CoachNote::count()));
    }
}

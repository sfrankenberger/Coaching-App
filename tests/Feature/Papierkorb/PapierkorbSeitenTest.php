<?php

use App\Enums\Role;
use App\Filament\Coach\Pages\Papierkorb as CoachPapierkorb;
use App\Filament\Plattform\Pages\Papierkorb as PlattformPapierkorb;
use App\Filament\Plattform\Resources\Personen\Pages\ListPersonen;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
    $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
    $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
    $this->lea = User::factory()->create(['name' => 'Lea Coach']);
    $this->anna = User::factory()->create(['name' => 'Anna Muster']);
    $this->admin = User::factory()->create(['name' => 'Sebastian', 'is_platform_admin' => true]);
    $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
    $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active']);
    $this->a->users()->attach($this->admin, ['role' => Role::Owner->value, 'status' => 'active']);
    $this->actingAs($this->lea);
    app(CurrentTenant::class)->run($this->a, fn () => Program::create(['title' => 'Frühling', 'slug' => 'fruehling'])->delete());
    app(CurrentTenant::class)->run($this->b, fn () => Program::create(['title' => 'Kurs B', 'slug' => 'b'])->delete());
});

it('zeigt der Coachin ihren Papierkorb ohne Endgueltig-Knopf und holt zurueck', function () {
    $this->actingAs($this->lea)->get('http://a.test/coach/papierkorb')->assertOk()
        ->assertSee('Frühling')->assertSee('Lea Coach')->assertSee('Wiederherstellen')
        ->assertDontSee('Kurs B')->assertDontSee('Endgültig löschen');
    $this->actingAs($this->anna)->get('http://a.test/coach/papierkorb')->assertForbidden();

    app(CurrentTenant::class)->run($this->a, function () {
        Filament::setCurrentPanel(Filament::getPanel('coach'));
        $this->actingAs($this->lea);
        $id = Program::onlyTrashed()->first()->id;

        Livewire::test(CoachPapierkorb::class)->call('wiederherstellen', 'program', $id)->assertNotified('Wiederhergestellt');
        expect(Program::count())->toBe(1);

        Program::first()->delete();
        Livewire::test(CoachPapierkorb::class)->call('endgueltig', 'program', $id)->assertForbidden();
        expect(Program::onlyTrashed()->count())->toBe(1);
    });
});

it('zeigt der Plattform alles und loescht endgueltig', function () {
    $this->actingAs($this->admin)->get('http://a.test/plattform/papierkorb')->assertOk()
        ->assertSee('Frühling')->assertSee('Kurs B')->assertSee('Endgültig löschen')->assertSeeInOrder(['Mandant', 'B']);
    $this->actingAs($this->lea)->get('http://a.test/plattform/papierkorb')->assertForbidden();

    app(CurrentTenant::class)->run($this->a, function () {
        Filament::setCurrentPanel(Filament::getPanel('plattform'));
        $this->actingAs($this->admin);
        $b = Program::withoutGlobalScope(TenantScope::class)->onlyTrashed()->where('slug', 'like', 'b~%')->first();

        Livewire::test(PlattformPapierkorb::class)->set('typ', 'program')->call('endgueltig', 'program', $b->id)->assertNotified('Endgültig gelöscht');
        expect(Program::withoutGlobalScope(TenantScope::class)->withTrashed()->count())->toBe(1);
    });
});

it('loescht in der Plattform eine Person nur mit eingetippter Adresse', function () {
    $this->actingAs($this->admin)->get('http://a.test/plattform/personen')->assertOk()->assertSee('Anna Muster');

    app(CurrentTenant::class)->run($this->a, function () {
        Filament::setCurrentPanel(Filament::getPanel('plattform'));
        $this->actingAs($this->admin);

        Livewire::test(ListPersonen::class)
            ->callTableAction('endgueltigLoeschen', $this->anna, data: ['bestaetigung' => 'falsch@example.com'])
            ->assertHasTableActionErrors(['bestaetigung']);
        expect(User::find($this->anna->id))->not->toBeNull();

        Livewire::test(ListPersonen::class)
            ->callTableAction('endgueltigLoeschen', $this->anna, data: ['bestaetigung' => $this->anna->email])
            ->assertHasNoTableActionErrors()->assertNotified('Person endgültig gelöscht');
        expect(User::find($this->anna->id))->toBeNull();

        Livewire::test(ListPersonen::class)->assertTableActionHidden('endgueltigLoeschen', $this->admin);
    });
});

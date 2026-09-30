<?php

use App\Enums\Role;
use App\Filament\Coach\Resources\Protokoll\Pages\ListProtokoll;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Protokoll;
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
    $this->b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
    $this->lea = User::factory()->create(['name' => 'Lea Coach']);
    $this->anna = User::factory()->create(['name' => 'Anna Muster']);
    $this->bea = User::factory()->create(['name' => 'Bea Andere']);
    $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
    $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active']);
    $this->b->users()->attach($this->bea, ['role' => Role::Owner->value, 'status' => 'active']);
    Protokoll::withoutGlobalScope(TenantScope::class)->delete();

    $this->actingAs($this->lea);
    app(CurrentTenant::class)->run($this->a, fn () => Program::create(['title' => 'Frühling', 'slug' => 'fruehling'])->update(['title' => 'Sommer']));
    $this->actingAs($this->bea);
    app(CurrentTenant::class)->run($this->b, fn () => Program::create(['title' => 'Geheimprogramm B', 'slug' => 'b']));
    auth()->logout();
});

it('zeigt der Coachin den Verlauf ihres Mandanten, nicht den anderer', function () {
    $this->actingAs($this->lea)->get('http://a.test/coach/verlauf')->assertOk()
        ->assertSee('Programm «Sommer» geändert')->assertSee('Programm «Frühling» angelegt')->assertSee('Lea Coach')
        ->assertDontSee('Geheimprogramm B');

    $this->actingAs($this->anna)->get('http://a.test/coach/verlauf')->assertForbidden();
});

it('filtert nach Ereignis und zeigt Details mit Vorher und Nachher', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        Filament::setCurrentPanel(Filament::getPanel('coach'));
        $this->actingAs($this->lea);
        $geaendert = Protokoll::where('event', 'updated')->first();

        Livewire::test(ListProtokoll::class)
            ->assertCanSeeTableRecords(Protokoll::all())
            ->filterTable('event', 'updated')
            ->assertCanSeeTableRecords([$geaendert])
            ->assertCanNotSeeTableRecords(Protokoll::where('event', 'created')->get())
            ->mountTableAction('details', $geaendert);

        // Das Modal zeigt Vorher und Nachher je Feld
        $html = view('filament.protokoll.details', ['eintrag' => $geaendert])->render();
        expect($html)->toContain('title')->toContain('Frühling')->toContain('Sommer')->toContain('Programm Nr.');
    });
});

it('zeigt im Dossier den Verlauf der Person', function () {
    $this->actingAs($this->lea);
    $m = app(CurrentTenant::class)->run($this->a, fn () => Membership::where('user_id', $this->anna->id)->first());
    app(CurrentTenant::class)->run($this->a, fn () => $m->update(['status' => 'paused']));

    $this->get("http://a.test/coach/memberships/{$m->id}/dossier")->assertOk()
        ->assertSee('Verlauf')->assertSee('Zugang «Anna Muster» geändert')->assertSee('Ganzen Verlauf zeigen')
        ->assertDontSee('Programm «Sommer» geändert');
});

it('zeigt der Plattform alles, auch Eintraege ohne Mandant', function () {
    $admin = User::factory()->create(['is_platform_admin' => true, 'name' => 'Sebastian']);
    $this->a->users()->attach($admin, ['role' => Role::Owner->value, 'status' => 'active']);
    $this->actingAs($admin);
    $this->b->update(['name' => 'B neu']);

    $this->get('http://a.test/plattform/verlauf')->assertOk()
        ->assertSee('Sommer')->assertSee('Geheimprogramm B')->assertSee('Mandant «B neu» geändert')->assertSee('Plattform');

    $this->actingAs($this->lea)->get('http://a.test/plattform/verlauf')->assertForbidden();
});

<?php

use App\Enums\Role;
use App\Models\Anhang;
use App\Models\Comment;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Protokoll;
use App\Models\Question;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Papierkorb\Papierkorb;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
    $this->b = Tenant::create(['slug' => 'b', 'name' => 'B']);
    $this->lea = User::factory()->create(['name' => 'Lea Coach']);
    $this->anna = User::factory()->create(['name' => 'Anna Muster']);
    $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
    $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active']);
    $this->actingAs($this->lea);
});

function programmMitKindern(): Program
{
    $p = Program::create(['title' => 'Frühling', 'slug' => 'fruehling']);
    $s = $p->steps()->create(['title' => 'Woche 1', 'position' => 1]);
    $u = $p->units()->create(['title' => 'Einheit 1', 'step_id' => $s->id, 'position' => 1]);
    $u->exercises()->create(['type' => 'text', 'prompt' => 'Was willst du?', 'position' => 1]);

    return $p;
}

it('verschiebt ein Programm samt Schritten, Einheiten und Uebungen in den Papierkorb und holt alles zurueck', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        $p = programmMitKindern();

        $p->delete();

        expect(Program::count())->toBe(0)->and(ProgramStep::count())->toBe(0)->and(Unit::count())->toBe(0)
            ->and(Program::onlyTrashed()->count())->toBe(1)->and(Unit::onlyTrashed()->count())->toBe(1)
            ->and(Protokoll::where('event', 'deleted')->count())->toBe(4);

        // der Slug ist frei
        $neu = Program::create(['title' => 'Frühling neu', 'slug' => 'fruehling']);
        expect(Program::withTrashed()->find($p->id)->slug)->toStartWith('fruehling~geloescht-');

        $neu->forceDelete();
        Program::withTrashed()->find($p->id)->restore();

        expect(Program::count())->toBe(1)->and(Program::first()->slug)->toBe('fruehling')
            ->and(ProgramStep::count())->toBe(1)->and(Unit::count())->toBe(1)
            ->and(Unit::first()->exercises()->count())->toBe(1)
            ->and(Protokoll::where('event', 'restored')->count())->toBe(4);
    });
});

it('haengt beim Zurueckholen ein Suffix an, wenn der Slug inzwischen vergeben ist', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        $alt = Program::create(['title' => 'Alt', 'slug' => 'kurs']);
        $alt->delete();
        Program::create(['title' => 'Neu', 'slug' => 'kurs']);

        $alt->restore();

        expect($alt->fresh()->slug)->toStartWith('kurs-')->and(Program::count())->toBe(2);
    });
});

it('loescht beim endgueltigen Loeschen die Kinder mit und protokolliert es als endgueltig', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        $p = programmMitKindern();
        $p->delete();

        app(Papierkorb::class)->endgueltig(Program::onlyTrashed()->first());

        expect(Program::withTrashed()->count())->toBe(0)->and(Unit::withTrashed()->count())->toBe(0)
            ->and(Protokoll::where('description', 'endgültig gelöscht')->count())->toBe(4);
    });
});

it('leert nach 90 Tagen nur, was alt genug ist, ueber alle Mandanten', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        Task::create(['user_id' => $this->anna->id, 'title' => 'Alt', 'visibility' => 'coach'])->delete();
        Task::create(['user_id' => $this->anna->id, 'title' => 'Frisch', 'visibility' => 'coach'])->delete();
        Task::onlyTrashed()->where('title', 'Alt')->update(['deleted_at' => now()->subDays(91)]);
    });
    app(CurrentTenant::class)->run($this->b, fn () => Program::create(['title' => 'B alt', 'slug' => 'b'])->delete());
    Program::onlyTrashed()->withoutGlobalScope(TenantScope::class)->update(['deleted_at' => now()->subDays(100)]);

    Artisan::call('papierkorb:leeren', ['--trocken' => true]);
    expect(Task::withoutGlobalScope(TenantScope::class)->withTrashed()->count())->toBe(2);

    Artisan::call('papierkorb:leeren');

    expect(Task::withoutGlobalScope(TenantScope::class)->withTrashed()->pluck('title')->all())->toBe(['Frisch'])
        ->and(Program::withoutGlobalScope(TenantScope::class)->withTrashed()->count())->toBe(0);
});

it('nimmt einen entfernten Zugang aus den Beziehungen und holt ihn samt Einstellungen zurueck', function () {
    $m = app(CurrentTenant::class)->run($this->a, function () {
        $m = Membership::where('user_id', $this->anna->id)->first();
        $m->update(['settings' => ['ansicht' => 'arbeitsplatz']]);
        $m->delete();

        return $m;
    });

    expect($this->a->users()->pluck('users.id')->all())->toBe([$this->lea->id])
        ->and($this->anna->tenants()->count())->toBe(0)
        ->and($this->anna->membershipIn($this->a))->toBeNull()
        ->and($this->anna->roleIn($this->a))->toBeNull();

    $zurueck = app(CurrentTenant::class)->run($this->a, fn () => Membership::anlegen(['user_id' => $this->anna->id, 'role' => Role::Member->value, 'status' => 'active']));

    expect($zurueck->id)->toBe($m->id)
        ->and($zurueck->setting('ansicht'))->toBe('arbeitsplatz')
        ->and($zurueck->role)->toBe(Role::Member)
        ->and($this->anna->fresh()->membershipIn($this->a)?->id)->toBe($m->id);
});

it('raeumt beim erneuten Anhaengen einen Zugang im Papierkorb weg statt an der Eindeutigkeit zu scheitern', function () {
    app(CurrentTenant::class)->run($this->a, fn () => Membership::where('user_id', $this->anna->id)->first()->delete());

    $this->a->users()->attach($this->anna, ['role' => Role::Client->value, 'status' => 'active']);

    expect(Membership::withoutGlobalScope(TenantScope::class)->withTrashed()->where('user_id', $this->anna->id)->count())->toBe(1)
        ->and($this->anna->roleIn($this->a))->toBe(Role::Client);
});

it('behaelt Anhaenge und Antworten im Papierkorb und entfernt sie erst endgueltig', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        $frage = Question::create(['user_id' => $this->anna->id, 'title' => 'Wie geht Atmen?']);
        $frage->answers()->create(['user_id' => $this->lea->id, 'body' => 'Langsam.']);
        $aufgabe = Task::create(['user_id' => $this->anna->id, 'title' => 'Lesen', 'visibility' => 'coach']);
        Anhang::create(['anhang_an_type' => 'task', 'anhang_an_id' => $aufgabe->id, 'ziel_type' => 'question', 'ziel_id' => $frage->id, 'position' => 0]);

        $frage->delete();
        $aufgabe->delete();
        expect($frage->answers()->count())->toBe(1)->and(Anhang::count())->toBe(1);

        $aufgabe->restore();
        expect($aufgabe->anhaenge()->count())->toBe(1);

        $frage->forceDelete();
        $aufgabe->forceDelete();
        expect(Comment::withTrashed()->count())->toBe(0)->and(Anhang::count())->toBe(0);
    });
});

it('zeigt Mandant B nichts aus dem Papierkorb von A', function () {
    app(CurrentTenant::class)->run($this->a, fn () => Program::create(['title' => 'Geheim', 'slug' => 'geheim'])->delete());

    expect(app(CurrentTenant::class)->run($this->a, fn () => app(Papierkorb::class)->eintraege())->pluck('titel')->all())->toBe(['Geheim'])
        ->and(app(CurrentTenant::class)->run($this->b, fn () => app(Papierkorb::class)->eintraege()))->toHaveCount(0)
        ->and(app(CurrentTenant::class)->run($this->b, fn () => app(Papierkorb::class)->finden('program', Program::withoutGlobalScope(TenantScope::class)->withTrashed()->first()->id)))->toBeNull();
});

it('listet nur Wurzeln, mit Person und wer geloescht hat', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        programmMitKindern()->delete();
        Task::create(['user_id' => $this->anna->id, 'title' => 'Ganz privat', 'visibility' => 'private'])->delete();

        $zeilen = app(Papierkorb::class)->eintraege();

        $je = $zeilen->keyBy('typName');
        expect($je->keys()->sort()->values()->all())->toBe(['Aufgabe', 'Programm'])
            ->and($je['Aufgabe']['titel'])->toBe('(privater Eintrag)')
            ->and($je['Aufgabe']['person'])->toBe('Anna Muster')
            ->and($je['Programm']['von'])->toBe('Lea Coach')
            ->and(app(Papierkorb::class)->eintraege(typ: 'program'))->toHaveCount(1);
    });
});

it('loescht eine Person endgueltig mit allem, was ihr gehoert', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    app(CurrentTenant::class)->run($this->a, fn () => Task::create(['user_id' => $this->anna->id, 'title' => 'Bleibt nicht', 'visibility' => 'coach']));

    app(Papierkorb::class)->personLoeschen($this->anna, $admin);

    expect(User::find($this->anna->id))->toBeNull()
        ->and(Membership::withoutGlobalScope(TenantScope::class)->withTrashed()->where('user_id', $this->anna->id)->count())->toBe(0)
        ->and(Task::withoutGlobalScope(TenantScope::class)->withTrashed()->count())->toBe(0)
        ->and(Protokoll::withoutGlobalScope(TenantScope::class)->where('subject_type', 'user')->where('event', 'deleted')->count())->toBe(1);
    expect(fn () => app(Papierkorb::class)->personLoeschen($admin, $admin))->toThrow(RuntimeException::class);
});

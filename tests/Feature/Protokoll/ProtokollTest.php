<?php

use App\Enums\Role;
use App\Models\JournalEntry;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Protokoll;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
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
    // Zugaenge aus dem Setup sind schon protokolliert, die Tests wollen bei null anfangen
    Protokoll::withoutGlobalScope(TenantScope::class)->delete();
});

it('haelt Anlegen, Aendern und Loeschen mit Mandant, Person und Verursacherin fest', function () {
    $this->actingAs($this->lea);

    app(CurrentTenant::class)->run($this->a, function () {
        $p = Program::create(['title' => 'Frühling', 'slug' => 'fruehling']);
        $p->update(['title' => 'Frühling 2027']);
        $p->update(['title' => 'Frühling 2027']); // nichts geaendert: kein Eintrag
        $p->delete();

        $eintraege = Protokoll::orderBy('id')->get();
        expect($eintraege)->toHaveCount(3)
            ->and($eintraege->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
            ->and($eintraege->pluck('description')->all())->toBe(['angelegt', 'geändert', 'gelöscht'])
            ->and($eintraege[0]->tenant_id)->toBe($this->a->id)
            ->and($eintraege[0]->causer_id)->toBe($this->lea->id)
            ->and($eintraege[0]->getProperty('titel'))->toBe('Frühling')
            ->and($eintraege[0]->getProperty('quelle'))->toBe('konsole')
            ->and($eintraege[1]->attribute_changes->get('old'))->toBe(['title' => 'Frühling'])
            ->and($eintraege[1]->attribute_changes->get('attributes'))->toBe(['title' => 'Frühling 2027'])
            ->and($eintraege[1]->satz())->toBe('Programm «Frühling 2027» geändert')
            ->and($eintraege[1]->wer())->toBe('Lea Coach')
            ->and($eintraege[2]->titel())->toBe('Frühling 2027');
    });
});

it('setzt die Person aus user_id und maskiert sensible Felder', function () {
    $this->actingAs($this->anna);

    app(CurrentTenant::class)->run($this->a, function () {
        $j = JournalEntry::create(['user_id' => $this->anna->id, 'title' => 'Heute', 'body' => 'Sehr privat', 'visibility' => 'coach']);
        $j->update(['body' => 'Noch privater']);

        [$anlegen, $aendern] = Protokoll::orderBy('id')->get();
        expect($anlegen->person_id)->toBe($this->anna->id)
            ->and($anlegen->attribute_changes->get('attributes')['body'])->toBe(Protokoll::MASKE)
            ->and($anlegen->attribute_changes->get('attributes')['title'])->toBe('Heute')
            ->and($aendern->attribute_changes->get('old'))->toBe(['body' => Protokoll::MASKE])
            ->and($aendern->aenderungen()[0])->toMatchArray(['feld' => 'body', 'maskiert' => true]);
        expect(json_encode(Protokoll::all()))->not->toContain('Sehr privat')->not->toContain('Noch privater');
    });
});

it('ignoriert reine Zeitstempel-Aenderungen', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        $m = Membership::where('user_id', $this->anna->id)->first();
        Protokoll::query()->delete();

        $m->update(['last_seen_at' => now()]);
        expect(Protokoll::count())->toBe(0);

        $m->update(['status' => 'paused']);
        expect(Protokoll::count())->toBe(1)
            ->and(Protokoll::first()->person_id)->toBe($this->anna->id)
            ->and(Protokoll::first()->satz())->toContain('Zugang');
    });
});

it('maskiert bei Personen Passwort und Telefon, haelt aber Namen fest', function () {
    $this->anna->update(['name' => 'Anna Neu', 'password' => 'geheim', 'phone' => '+41 79 000 00 00']);

    $e = Protokoll::withoutGlobalScope(TenantScope::class)->latest('id')->first();
    expect($e->tenant_id)->toBeNull()
        ->and($e->person_id)->toBe($this->anna->id)
        ->and($e->attribute_changes->get('attributes'))->toMatchArray(['name' => 'Anna Neu', 'password' => Protokoll::MASKE, 'phone' => Protokoll::MASKE])
        ->and($e->typ())->toBe('Person');
});

it('zeigt Mandant B nichts von Mandant A', function () {
    app(CurrentTenant::class)->run($this->a, fn () => Task::create(['user_id' => $this->anna->id, 'title' => 'Atmen']));

    expect(app(CurrentTenant::class)->run($this->a, fn () => Protokoll::count()))->toBe(1)
        ->and(app(CurrentTenant::class)->run($this->b, fn () => Protokoll::count()))->toBe(0)
        ->and(Protokoll::count())->toBe(0); // ohne Mandant: nichts
});

it('nennt System, wenn niemand angemeldet ist', function () {
    app(CurrentTenant::class)->run($this->a, fn () => Task::create(['user_id' => $this->anna->id, 'title' => 'Atmen']));

    expect(app(CurrentTenant::class)->run($this->a, fn () => Protokoll::first()->wer()))->toBe('System (Konsole)');
});

it('raeumt Eintraege nach 730 Tagen auf', function () {
    app(CurrentTenant::class)->run($this->a, function () {
        Task::create(['user_id' => $this->anna->id, 'title' => 'Alt', 'visibility' => 'coach']);
        Protokoll::query()->update(['created_at' => now()->subDays(731)]);
        Task::create(['user_id' => $this->anna->id, 'title' => 'Neu', 'visibility' => 'coach']);

        Artisan::call('activitylog:clean');

        expect(Protokoll::count())->toBe(1)->and(Protokoll::first()->titel())->toBe('Neu');
    });
});

it('verbirgt bei privaten Eintraegen Titel und Inhalt, zeigt aber, dass sich etwas getan hat', function () {
    $this->actingAs($this->anna);

    app(CurrentTenant::class)->run($this->a, function () {
        $t = Task::create(['user_id' => $this->anna->id, 'title' => 'Ganz geheim', 'body' => 'Nur fuer mich', 'visibility' => 'private']);
        $t->update(['title' => 'Immer noch geheim', 'visibility' => 'coach']);

        [$anlegen, $teilen] = Protokoll::orderBy('id')->get();
        expect($anlegen->satz())->toBe('Aufgabe «(privater Eintrag)» angelegt')
            ->and(json_encode($anlegen))->not->toContain('geheim')->not->toContain('Nur fuer mich')
            ->and(collect($anlegen->aenderungen())->pluck('feld'))->toContain('title')
            // sobald geteilt, ist der Titel sichtbar
            ->and($teilen->titel())->toBe('Immer noch geheim');
    });
});

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Coach\Resources\Programs\Pages\ListPrograms;
use App\Filament\Coach\Resources\Programs\Tables\ProgramsTable;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgrammeListeTest extends TestCase
{
    use RefreshDatabase;

    public function test_liste_ohne_1_zu_1_mit_status(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $lea = User::factory()->create();
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->actingAs($lea);

        app(CurrentTenant::class)->run($a, function () {
            $offen = Program::create(['title' => 'Hybrid offen', 'slug' => 'hybrid', 'type' => 'hybrid', 'starts_at' => now()->subWeek()]);
            $intern = Program::create(['title' => 'Kurs intern', 'slug' => 'intern', 'type' => 'selfpaced', 'is_internal' => true]);
            $entwurf = Program::create(['title' => 'Kurs Entwurf', 'slug' => 'entwurf', 'type' => 'selfpaced', 'is_published' => false, 'ends_at' => now()->subDay()]);
            $eins = Program::create(['title' => '1:1 Anna', 'slug' => 'eins', 'type' => 'one_on_one']);

            $this->assertSame(['offen', 'intern', 'entwurf'], [ProgramsTable::status($offen), ProgramsTable::status($intern), ProgramsTable::status($entwurf)]);
            $this->assertStringStartsWith('läuft seit', ProgramsTable::laufzeit($offen));
            $this->assertStringStartsWith('beendet', ProgramsTable::laufzeit($entwurf));
            $this->assertNull(ProgramsTable::laufzeit($intern));

            Livewire::test(ListPrograms::class)
                ->assertCanSeeTableRecords([$offen, $intern, $entwurf])
                ->assertCanNotSeeTableRecords([$eins])
                ->assertSee(['Veröffentlicht', 'Nur intern', 'Nicht veröffentlicht'])
                ->assertDontSee('1:1 Anna')
                ->filterTable('status', 'entwurf')
                ->assertCanSeeTableRecords([$entwurf])
                ->assertCanNotSeeTableRecords([$offen, $intern]);
        });
    }
}

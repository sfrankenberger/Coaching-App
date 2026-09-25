<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Enums\Role;
use App\Filament\Coach\Resources\Memberships\Pages\Dossier;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TerminvorschlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_coachin_schlaegt_zeiten_vor_und_person_bucht_eine(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 08:00:00', 'UTC'));
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $lea = User::factory()->create(['name' => 'Lea Coach']);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $a->users()->attach($anna, ['role' => Role::Client->value, 'status' => 'active']);
        $a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active']);
        $m = app(CurrentTenant::class)->run($a, fn () => Membership::where('user_id', $anna->id)->first());

        // Im Dossier in Ortszeit eingegeben
        app(CurrentTenant::class)->run($a, function () use ($lea, $m) {
            Filament::setCurrentPanel(Filament::getPanel('coach'));
            FilamentTimezone::set('Europe/Zurich');
            $this->actingAs($lea);
            Livewire::test(Dossier::class, ['record' => $m->id])
                ->callAction('zeiten', data: ['zeiten' => [['start' => '2026-10-06 14:00:00'], ['start' => '2026-10-07 09:30:00']], 'dauer' => 50, 'text' => 'Passt dir eine?'])
                ->assertHasNoActionErrors();
            FilamentTimezone::set(null);
        });

        $vorschlag = app(CurrentTenant::class)->run($a, fn () => Message::whereNotNull('meta')->first());
        $this->assertSame(['2026-10-06T12:00:00+00:00', '2026-10-07T07:30:00+00:00'], $vorschlag->meta['vorschlaege']);
        $conv = $vorschlag->conversation_id;

        $this->actingAs($anna)->get("http://a.test/gespraech/{$conv}")->assertOk()->assertSee('Passt dir eine?')->assertSee('14:00');
        $this->actingAs($bea)->post("http://a.test/nachricht/{$vorschlag->id}/termin", ['i' => 0])->assertForbidden();
        $this->actingAs($lea)->post("http://a.test/nachricht/{$vorschlag->id}/termin", ['i' => 0])->assertForbidden();

        $this->actingAs($anna)->post("http://a.test/nachricht/{$vorschlag->id}/termin", ['i' => 0])->assertRedirect("http://a.test/gespraech/{$conv}");
        $this->actingAs($anna)->post("http://a.test/nachricht/{$vorschlag->id}/termin", ['i' => 1])->assertStatus(409);

        $this->assertSame('2026-10-06 12:00:00', DB::table('events')->value('starts_at'));
        $this->assertSame('2026-10-06 12:50:00', DB::table('events')->value('ends_at'));
        app(CurrentTenant::class)->run($a, function () use ($anna) {
            $e = Event::first();
            $this->assertSame($anna->id, $e->user_id);
            $this->assertSame('one_on_one', $e->type);
            $bestaetigung = Message::latest('id')->first();
            $this->assertSame($anna->id, $bestaetigung->user_id);
            $this->assertSame('event', $bestaetigung->ref_type);
            $this->assertStringContainsString('6. Oktober, 14:00', $bestaetigung->body);
        });
        $this->actingAs($lea)->get("http://a.test/gespraech/{$conv}")->assertOk()->assertSee('gebucht');
    }
}

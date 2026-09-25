<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** In der Datenbank UTC, in der App die Ortszeit des Mandanten. */
class OrtszeitTest extends TestCase
{
    use RefreshDatabase;

    public function test_termin_zeigt_ortszeit_und_speichert_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 08:00:00', 'UTC'));
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $anna = User::factory()->create();
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);

        $e = app(CurrentTenant::class)->run($a, function () use ($anna) {
            $e = Event::create(['title' => 'Sitzung', 'type' => 'one_on_one', 'user_id' => $anna->id, 'starts_at' => Carbon::parse('2026-09-28 18:00:00', 'UTC'), 'is_published' => true]);

            return Event::find($e->id);
        });

        // Gelesen in Ortszeit, gespeichert in UTC
        $this->assertSame('2026-09-28 20:00', app(CurrentTenant::class)->run($a, fn () => $e->starts_at->format('Y-m-d H:i')));
        $this->assertSame('2026-09-28 18:00', $e->starts_at->format('Y-m-d H:i'), 'ohne Mandant bleibt es UTC');
        $this->assertSame('2026-09-28 18:00:00', DB::table('events')->value('starts_at'));

        // Ortszeit zurueckgeschrieben bleibt UTC, auch ohne Cast und in Abfragen
        app(CurrentTenant::class)->run($a, function () use ($e, $anna) {
            $e->forceFill(['starts_at' => $e->starts_at])->save();
            $r = new EventAttendee(['event_id' => $e->id, 'user_id' => $anna->id, 'status' => 'declined']);
            $r->timestamps = false;
            $r->created_at = $e->starts_at;
            $r->updated_at = $e->starts_at;
            $r->save();
            $this->assertSame(1, Event::where('starts_at', '>=', $e->starts_at)->count());
            $this->assertSame(0, Event::where('starts_at', '>', $e->starts_at)->count());
        });
        $this->assertSame('2026-09-28 18:00:00', DB::table('events')->value('starts_at'));
        $this->assertSame('2026-09-28 18:00:00', DB::table('event_attendees')->value('updated_at'));

        $this->actingAs($anna)->get('http://a.test/termine')->assertOk()->assertSee('20:00')->assertDontSee('18:00');
    }

    public function test_coach_bereich_nimmt_ortszeit_entgegen(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $lea = User::factory()->create();
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);

        app(CurrentTenant::class)->run($a, function () use ($lea, $a) {
            \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coach'));
            \Filament\Support\Facades\FilamentTimezone::set($a->timezone);
            $this->actingAs($lea);
            \Livewire\Livewire::test(\App\Filament\Coach\Resources\Events\Pages\CreateEvent::class)
                ->fillForm(['title' => 'Call', 'type' => 'group_call', 'starts_at' => '2026-10-05 20:00:00'])
                ->call('create')->assertHasNoFormErrors();
            \Filament\Support\Facades\FilamentTimezone::set(null);
        });

        $this->assertSame('2026-10-05 18:00:00', DB::table('events')->value('starts_at'), '20:00 Zuerich im Sommer = 18:00 UTC');
    }
}

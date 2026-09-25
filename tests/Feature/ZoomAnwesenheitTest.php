<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Zoom\Anwesenheit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZoomAnwesenheitTest extends TestCase
{
    use RefreshDatabase;

    public function test_teilnehmerliste_wird_zugeordnet(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 21:00:00', 'UTC'));
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich', 'settings' => ['zoom' => [
            'account_id' => 'acc', 'client_id' => 'cid', 'client_secret' => 'sec', 'hosts' => 'Lea Coach, lea@a.test', 'min_minutes' => 10,
        ]]]);
        $leute = [];
        foreach (['Lea Coach' => Role::Owner, 'Anna Muster' => Role::Member, 'Bea Beispiel' => Role::Member, 'Carla Kurz' => Role::Member, 'Dora Nein' => Role::Member, 'Eva Fremd' => Role::Member] as $name => $rolle) {
            $u = User::factory()->create(['name' => $name, 'email' => Str::slug($name).'@mail.test']);
            $a->users()->attach($u, ['role' => $rolle->value, 'status' => 'active']);
            $leute[$name] = $u;
        }
        $call = app(CurrentTenant::class)->run($a, function () use ($leute) {
            $k = Program::create(['slug' => 'kurs', 'title' => 'Kurs']);
            foreach (['Anna Muster', 'Bea Beispiel', 'Carla Kurz', 'Dora Nein'] as $n) {
                ProgramMember::create(['program_id' => $k->id, 'user_id' => $leute[$n]->id]);
            }
            $e = Event::withoutEvents(fn () => Event::create(['tenant_id' => app(CurrentTenant::class)->id(), 'program_id' => $k->id, 'title' => 'Call', 'type' => 'group_call',
                'starts_at' => '2026-09-21 18:00:00', 'zoom_url' => 'https://us02web.zoom.us/j/81234567890?pwd=x', 'is_published' => true]));
            EventAttendee::create(['event_id' => $e->id, 'user_id' => $leute['Dora Nein']->id, 'status' => 'declined']);

            return $e;
        });

        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok']),
            'api.zoom.us/v2/past_meetings/81234567890/instances' => Http::response(['meetings' => [
                ['uuid' => 'alt==', 'start_time' => '2026-09-14T17:58:00Z'],
                ['uuid' => 'ab/c==', 'start_time' => '2026-09-21T17:57:00Z'],
            ]]),
            'api.zoom.us/v2/past_meetings/ab%252Fc%253D%253D/participants*' => Http::response(['participants' => [
                ['name' => 'Lea Coach', 'user_email' => 'lea@a.test', 'duration' => 3600],
                ['name' => 'Anna', 'user_email' => 'anna-muster@mail.test', 'duration' => 1800],
                ['name' => 'Anna', 'user_email' => 'anna-muster@mail.test', 'duration' => 1500],
                ['name' => 'bea', 'user_email' => '', 'duration' => 3000],
                ['name' => 'Carla K.', 'user_email' => '', 'duration' => 300],
                ['name' => 'Dora Nein', 'user_email' => '', 'duration' => 3000],
                ['name' => 'Unbekannt', 'user_email' => '', 'duration' => 3000],
            ]]),
        ]);

        $b = app(CurrentTenant::class)->run($a, fn () => app(Anwesenheit::class)->abgleich($call));

        $this->assertNull($b['fehler']);
        $this->assertSame(['Lea Coach (60 Min)'], $b['gastgeber']);
        $this->assertSame(['Anna (55 Min) = Anna Muster'], $b['gesetzt'], 'ueber die Mail, Minuten zusammengezaehlt');
        $this->assertSame(['bea (50 Min) = Bea Beispiel (über den Vornamen)'], $b['unsicher']);
        $this->assertCount(2, $b['uebersprungen'], 'Carla zu kurz, Dora hatte abgesagt');
        $this->assertSame(['Unbekannt (50 Min)'], $b['fremd']);

        app(CurrentTenant::class)->run($a, function () use ($call, $leute) {
            $this->assertSame('attended', EventAttendee::where('event_id', $call->id)->where('user_id', $leute['Anna Muster']->id)->value('status'));
            $this->assertSame('attended', EventAttendee::where('event_id', $call->id)->where('user_id', $leute['Bea Beispiel']->id)->value('status'));
            $this->assertSame('declined', EventAttendee::where('event_id', $call->id)->where('user_id', $leute['Dora Nein']->id)->value('status'));
            $this->assertNull(EventAttendee::where('event_id', $call->id)->where('user_id', $leute['Carla Kurz']->id)->first());
            $this->assertSame('bea', Membership::where('user_id', $leute['Bea Beispiel']->id)->first()->setting('zoom_name'), 'Zoom-Name gemerkt');
            $this->assertNotEmpty($call->fresh()->settings['zoom']['abgeglichen']);
            $this->assertCount(0, app(Anwesenheit::class)->termine(), 'nicht zweimal');
        });
    }
}

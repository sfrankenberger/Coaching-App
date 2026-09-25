<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TestbetriebTest extends TestCase
{
    use RefreshDatabase;

    public function test_im_testbetrieb_nur_freigegebene_adressen(): void
    {
        Notification::fake();
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['notifications' => ['test_only' => true, 'test_emails' => ['Seb@Example.com']]]]);
        $seb = User::factory()->create(['email' => 'seb@example.com']);
        $anna = User::factory()->create(['email' => 'anna@example.com']);
        foreach ([$seb, $anna] as $u) {
            $a->users()->attach($u, ['role' => Role::Member->value, 'status' => 'active']);
        }

        $report = app(CurrentTenant::class)->run($a, fn () => app(Notifier::class)->send([$seb, $anna], new Nachricht('Hallo', 'Text')));
        $this->assertSame(['mail'], $report[$seb->id]);
        $this->assertSame([], $report[$anna->id]);
        Notification::assertSentTo($seb, AppNotification::class);
        Notification::assertNotSentTo($anna, AppNotification::class);

        // Testbetrieb aus: alle
        $a->forceFill(['settings' => ['notifications' => ['test_only' => false]]])->save();
        app(CurrentTenant::class)->run($a->fresh(), fn () => app(Notifier::class)->send([$anna], new Nachricht('Hallo', 'Text')));
        Notification::assertSentTo($anna, AppNotification::class);
    }

    public function test_seeder_ueberschreibt_spaeter_gesetzte_werte_nicht(): void
    {
        $this->seed(TenantSeeder::class);
        $lea = Tenant::where('slug', 'lea')->first();
        $this->assertTrue($lea->setting('notifications.test_only'));
        $this->assertSame('#B4795F', $lea->branding['primary']);

        $settings = $lea->settings;
        data_set($settings, 'push.vapid', ['public' => 'pub', 'private' => 'priv']);
        data_set($settings, 'bridge.secret', 'geheim');
        data_set($settings, 'notifications.test_emails', ['a@example.com', 'b@example.com']);
        data_set($settings, 'mail.from_name', 'Andere');
        $branding = $lea->branding;
        $branding['primary'] = '#000000';
        $lea->forceFill(['settings' => $settings, 'branding' => $branding])->save();

        $this->seed(TenantSeeder::class);
        $lea = $lea->fresh();
        $this->assertSame('pub', $lea->setting('push.vapid.public'));
        $this->assertSame('geheim', $lea->setting('bridge.secret'));
        $this->assertSame(['a@example.com', 'b@example.com'], $lea->setting('notifications.test_emails'));
        $this->assertSame('Andere', $lea->setting('mail.from_name'));
        $this->assertSame('hallo@leawernli.ch', $lea->setting('mail.from_address'), 'fehlende Vorgaben ergaenzt');
        $this->assertSame('#000000', $lea->branding['primary']);
        $this->assertSame(1, Tenant::where('slug', 'lea')->count());
        $this->assertSame(2, $lea->domains()->count());
    }
}

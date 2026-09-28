<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Import\WordPress\PushImport;
use App\Import\WordPress\WordPressSource;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Push-Abos und VAPID-Schluessel aus dem alten Bereich uebernehmen. */
class ImportPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_abos_und_schluessel_kommen_mit(): void
    {
        config(['database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_', 'foreign_key_constraints' => false]]);
        $wp = DB::connection('wordpress');
        $wp->getSchemaBuilder()->create('users', function ($t) {
            $t->increments('ID');
            $t->string('user_login');
            $t->string('user_email');
            $t->string('display_name');
            $t->timestamp('user_registered')->nullable();
        });
        $wp->getSchemaBuilder()->create('usermeta', function ($t) {
            $t->increments('umeta_id');
            $t->unsignedBigInteger('user_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
        $wp->getSchemaBuilder()->create('options', function ($t) {
            $t->increments('option_id');
            $t->string('option_name');
            $t->text('option_value');
        });
        $wp->table('users')->insert([
            ['ID' => 2, 'user_login' => 'lea', 'user_email' => 'lea@test.ch', 'display_name' => 'Lea', 'user_registered' => now()],
            ['ID' => 21, 'user_login' => 'simone', 'user_email' => 'Simone@Test.ch', 'display_name' => 'Simone', 'user_registered' => now()],
            ['ID' => 99, 'user_login' => 'fremd', 'user_email' => 'fremd@test.ch', 'display_name' => 'Fremd', 'user_registered' => now()],
        ]);
        $abo = fn ($ep) => ['endpoint' => $ep, 'keys' => ['p256dh' => 'P'.$ep, 'auth' => 'A'.$ep], 'zeit' => time(), 'ua' => 'iPhone'];
        $wp->table('usermeta')->insert([
            ['user_id' => 2, 'meta_key' => 'lea_push_subs', 'meta_value' => serialize([md5('e1') => $abo('https://web.push.apple.com/e1')])],
            ['user_id' => 21, 'meta_key' => 'lea_push_subs', 'meta_value' => serialize([md5('e2') => $abo('https://web.push.apple.com/e2'), md5('e3') => $abo('https://fcm.googleapis.com/e3')])],
            ['user_id' => 99, 'meta_key' => 'lea_push_subs', 'meta_value' => serialize([md5('e9') => $abo('https://web.push.apple.com/e9')])],
        ]);
        $wp->table('options')->insert(['option_name' => 'lea_push_vapid', 'option_value' => serialize(['publicKey' => 'PUB', 'privateKey' => 'PRIV'])]);

        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $lea = User::factory()->create(['email' => 'lea@test.ch']);
        $simone = User::factory()->create(['email' => 'simone@test.ch']);
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $a->users()->attach($simone, ['role' => Role::Client->value, 'status' => 'active']);

        $stats = app(CurrentTenant::class)->run($a, fn () => (new PushImport($a, new WordPressSource, true))->run());
        $this->assertSame(['personen' => 2, 'abos' => 3, 'neu' => 3, 'ohne_konto' => 1, 'schluessel' => true], $stats);
        $this->assertSame(['public' => 'PUB', 'private' => 'PRIV'], $a->fresh()->setting('push.vapid'));
        app(CurrentTenant::class)->run($a, function () use ($simone) {
            $this->assertSame(3, PushSubscription::count());
            $s = PushSubscription::where('user_id', $simone->id)->orderBy('id')->get();
            $this->assertCount(2, $s);
            $this->assertSame('Phttps://web.push.apple.com/e2', $s[0]->p256dh);
            $this->assertSame(hash('sha256', 'https://web.push.apple.com/e2'), $s[0]->endpoint_hash);
        });

        // Zweiter Lauf: nichts doppelt
        $stats = app(CurrentTenant::class)->run($a, fn () => (new PushImport($a, new WordPressSource))->run());
        $this->assertSame(0, $stats['neu']);
        $this->assertSame(3, app(CurrentTenant::class)->run($a, fn () => PushSubscription::count()));
    }
}

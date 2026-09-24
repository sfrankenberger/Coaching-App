<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Import\WordPress\UsersImport;
use App\Import\WordPress\WordPressSource;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportUsersTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $lea;

    protected function setUp(): void
    {
        parent::setUp();

        // Nachgebaute WordPress-Datenbank (eigene SQLite im Speicher, Praefix wp_)
        config(['database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_', 'foreign_key_constraints' => false]]);
        $wp = DB::connection('wordpress');
        $wp->getSchemaBuilder()->create('users', function ($t) {
            $t->increments('ID');
            $t->string('user_login');
            $t->string('user_email');
            $t->string('display_name');
            $t->dateTime('user_registered');
        });
        $wp->getSchemaBuilder()->create('usermeta', function ($t) {
            $t->increments('umeta_id');
            $t->unsignedInteger('user_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
        $wp->getSchemaBuilder()->create('jet_rel_default', function ($t) {
            $t->increments('_ID');
            $t->string('rel_id');
            $t->unsignedInteger('parent_object_id');
            $t->unsignedInteger('child_object_id');
        });

        $this->lea = Tenant::create(['slug' => 'lea', 'name' => 'Lea', 'settings' => ['import' => ['wordpress' => [
            'owner_ids' => [2],
            'team_roles' => ['administrator', 'lea_redaktion'],
            'course_relation_id' => 13,
            'meta' => ['phone' => 'lea_telefon', 'reminders_off' => 'lea_te_aus', 'evening_mail_off' => 'lea_am_aus',
                'task_reminders_off' => 'lea_ap_erinnerung_aus', 'onboarding_seen' => 'lea_willkommen_gesehen', 'access' => 'lea_zugaenge'],
        ]]]]);

        $this->wpUser(1, 'sebastian@example.com', 'Sebastian', ['administrator']);
        $this->wpUser(2, 'lea@example.com', 'Lea Wernli', ['administrator'], ['first_name' => 'Lea', 'last_name' => 'Wernli']);
        $this->wpUser(3, 'andrea@example.com', 'Andrea', ['lea_redaktion']);
        $this->wpUser(4, 'anna@example.com', 'Anna', ['customer'], [
            'lea_telefon' => '079 111 22 33', 'lea_am_aus' => '1', 'lea_willkommen_gesehen' => '1758700000',
            'lea_zugaenge' => serialize([1234 => ['bis' => 0, 'quelle' => 'kauf']]),
        ]);
        $this->wpUser(5, 'bea@example.com', 'Bea', ['subscriber']);
        $wp->table('jet_rel_default')->insert(['rel_id' => '13', 'parent_object_id' => 5, 'child_object_id' => 999]);
        $this->wpUser(6, 'gast@example.com', 'Gast', ['subscriber']);
        $this->wpUser(7, 'kaputt', 'Ohne Mail', ['subscriber']);
        $this->wpUser(8, 'alt@example.com', 'Abgelaufen', ['customer'], ['lea_zugaenge' => serialize([1 => ['bis' => time() - 86400, 'quelle' => 'kauf']])]);
    }

    protected function wpUser(int $id, string $email, string $name, array $roles, array $meta = []): void
    {
        $wp = DB::connection('wordpress');
        $wp->table('users')->insert(['ID' => $id, 'user_login' => $email, 'user_email' => $email, 'display_name' => $name, 'user_registered' => '2025-01-0'.min($id, 9).' 10:00:00']);
        $meta['wp_capabilities'] = serialize(array_fill_keys($roles, true));
        foreach ($meta as $k => $v) {
            $wp->table('usermeta')->insert(['user_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        }
    }

    protected function import(bool $withGuests = false): array
    {
        return app(CurrentTenant::class)->run($this->lea, fn () => (new UsersImport($this->lea, new WordPressSource, $withGuests))->run());
    }

    public function test_rollen_werden_zugeordnet(): void
    {
        $stats = $this->import();

        $rolle = fn (string $email) => User::where('email', $email)->first()?->roleIn($this->lea);

        $this->assertSame(Role::Owner, $rolle('lea@example.com'));
        $this->assertSame(Role::Team, $rolle('sebastian@example.com'));
        $this->assertSame(Role::Team, $rolle('andrea@example.com'));
        $this->assertSame(Role::Member, $rolle('anna@example.com'), 'Zugang aus lea_zugaenge');
        $this->assertSame(Role::Member, $rolle('bea@example.com'), 'Zugang aus Relation 13');
        $this->assertNull($rolle('gast@example.com'), 'Gaeste ohne --with-guests nicht');
        $this->assertNull($rolle('alt@example.com'), 'abgelaufener Zugang = Gast');
        $this->assertNull(User::where('email', 'kaputt')->first());

        $this->assertSame(5, $stats['angelegt']);
        $this->assertSame(3, $stats['uebersprungen']);
    }

    public function test_gaeste_mit_schalter(): void
    {
        $this->import(withGuests: true);
        $this->assertSame(Role::Guest, User::where('email', 'gast@example.com')->first()->roleIn($this->lea));
    }

    public function test_name_telefon_schalter_und_legacy_id(): void
    {
        $this->import();

        $lea = User::where('email', 'lea@example.com')->first();
        $this->assertSame('Lea Wernli', $lea->name);

        $anna = User::where('email', 'anna@example.com')->first();
        $m = $anna->membershipIn($this->lea);
        $this->assertSame('079 111 22 33', $anna->phone);
        $this->assertSame('4', $m->legacy_id);
        $this->assertSame(['termine' => true, 'abendmail' => false, 'aufgaben' => true], $m->setting('notifications'));
        $this->assertNotNull($m->setting('onboarding_seen_at'));
        $this->assertSame('2025-01-04', $m->joined_at->toDateString());
    }

    public function test_import_ist_wiederholbar_und_ueberschreibt_eigene_aenderungen_nicht(): void
    {
        $this->import();
        $anna = User::where('email', 'anna@example.com')->first();
        $anna->update(['name' => 'Anna Muster', 'phone' => '079 999 99 99']);
        $anna->membershipIn($this->lea)->update(['status' => 'paused']);

        $stats = $this->import();

        $this->assertSame(0, $stats['angelegt']);
        $this->assertSame(5, $stats['aktualisiert']);
        $this->assertSame(5, User::count());
        $this->assertSame(5, Membership::withoutGlobalScopes()->count());

        $anna->refresh();
        $this->assertSame('Anna Muster', $anna->name);
        $this->assertSame('079 999 99 99', $anna->phone);
        $this->assertSame('paused', $anna->membershipIn($this->lea)->status);
    }

    public function test_import_in_mandant_b_beruehrt_a_nicht(): void
    {
        $this->import();
        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'settings' => ['import' => ['wordpress' => ['owner_ids' => [3]]]]]);

        app(CurrentTenant::class)->run($b, fn () => (new UsersImport($b, new WordPressSource))->run());

        $this->assertSame(5, User::count(), 'Personen sind plattformweit, keine Doppelten');
        $andrea = User::where('email', 'andrea@example.com')->first();
        $this->assertSame(Role::Team, $andrea->roleIn($this->lea));
        $this->assertSame(Role::Owner, $andrea->roleIn($b));
        $this->assertNull(User::where('email', 'anna@example.com')->first()->roleIn($b));
    }
}

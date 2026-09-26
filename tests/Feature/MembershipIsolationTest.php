<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\LoginToken;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regel 6: fuer jede mandantenfaehige Tabelle ein Test, dass Mandant B die Daten von A nicht sieht.
 */
class MembershipIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_memberships_und_login_tokens_sind_getrennt(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $user = User::factory()->create();
        $cur = app(CurrentTenant::class);

        $cur->run($a, function () use ($user) {
            Membership::create(['user_id' => $user->id, 'role' => Role::Member->value]);
            LoginToken::create(['user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addMinute()]);
        });

        $this->assertSame(1, $cur->run($a, fn () => Membership::count()));
        $this->assertSame(0, $cur->run($b, fn () => Membership::count()));
        $this->assertSame(1, $cur->run($a, fn () => LoginToken::count()));
        $this->assertSame(0, $cur->run($b, fn () => LoginToken::count()));
        $this->assertSame(0, Membership::count());
        $this->assertSame(0, LoginToken::count());
    }
}

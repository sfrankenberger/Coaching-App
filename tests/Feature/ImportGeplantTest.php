<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportGeplantTest extends TestCase
{
    use RefreshDatabase;

    public function test_nur_mandanten_mit_plan_und_nur_bekannte_teile(): void
    {
        config(['database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_', 'foreign_key_constraints' => false]]);
        $wp = DB::connection('wordpress');
        $wp->getSchemaBuilder()->create('posts', function ($t) {
            $t->increments('ID');
            $t->string('post_title');
            $t->string('post_name');
            $t->text('post_content')->nullable();
            $t->text('post_excerpt')->nullable();
            $t->string('post_status');
            $t->string('post_type');
            $t->dateTime('post_date')->nullable();
            $t->dateTime('post_modified')->nullable();
            $t->integer('post_author')->default(0);
            $t->integer('menu_order')->default(0);
        });
        $wp->getSchemaBuilder()->create('postmeta', function ($t) {
            $t->increments('meta_id');
            $t->unsignedInteger('post_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });
        $wp->getSchemaBuilder()->create('usermeta', function ($t) {
            $t->increments('umeta_id');
            $t->unsignedInteger('user_id');
            $t->string('meta_key');
            $t->text('meta_value')->nullable();
        });

        Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['import' => ['wordpress' => ['schedule' => ['inhalte', 'loeschen']]]]]);
        Tenant::create(['slug' => 'b', 'name' => 'B']);

        $this->artisan('import:geplant')
            ->expectsOutputToContain('a: inhalte')
            ->doesntExpectOutputToContain('b:')
            ->doesntExpectOutputToContain('loeschen')
            ->assertSuccessful();
    }
}

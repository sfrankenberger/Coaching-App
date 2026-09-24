<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('locale', 10)->default('de_CH');
            $table->string('timezone')->default('Europe/Zurich');
            $table->string('currency', 3)->default('CHF');
            $table->json('settings')->nullable();   // fachliche Einstellungen
            $table->json('branding')->nullable();   // Farben, Schriften, Logo, App-Icon
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->boolean('is_platform_admin')->default(false)->after('avatar_path');
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('active'); // active | paused | ended
            $table->string('legacy_id')->nullable();          // z. B. WordPress-User-ID beim Import
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'avatar_path', 'is_platform_admin']));
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};

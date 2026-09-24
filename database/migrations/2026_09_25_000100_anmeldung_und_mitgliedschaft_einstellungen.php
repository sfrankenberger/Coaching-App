<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Magic Link ist Standard, Passwort ist freiwillig.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Einmal-Links zum Anmelden. Der Token liegt nur als Hash in der Datenbank.
        Schema::create('login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('weiter')->nullable();     // Ziel nach der Anmeldung
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        // Einstellungen je Person und Mandant (Benachrichtigungsschalter, Einfuehrung gesehen).
        Schema::table('memberships', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', fn (Blueprint $t) => $t->dropColumn('settings'));
        Schema::dropIfExists('login_tokens');
        Schema::table('users', fn (Blueprint $t) => $t->string('password')->nullable(false)->change());
    }
};

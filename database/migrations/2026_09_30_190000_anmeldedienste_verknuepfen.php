<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Verknuepfte Anmeldedienste (Google, Apple) gehoeren zur Person (users, plattformweit),
| nicht zum Mandanten: darum ohne tenant_id, wie die Passkeys. Die Kennung beim Dienst
| bleibt gleich, auch wenn die Person bei Apple ihre Mailadresse verbirgt.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_id', 191);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth 2.1 fuer den MCP-Server: Claude, ChatGPT und andere Assistenten registrieren sich selbst (RFC 7591),
 * die Person meldet sich in der App an und erlaubt den Zugriff, der Assistent bekommt ein Sanctum-Token mit
 * der Faehigkeit mcp. Alles je Mandant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 40)->unique();
            $table->string('name', 120);
            $table->json('redirect_uris');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'client_id']);
        });

        Schema::create('oauth_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->string('redirect_uri', 500);
            $table->string('code_challenge', 128);
            $table->string('scope', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('access_token_id')->nullable();   // personal_access_tokens.id, wird beim Erneuern ersetzt
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_codes');
        Schema::dropIfExists('oauth_clients');
    }
};

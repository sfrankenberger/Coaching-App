<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wissensspeicher (Second Brain) je Mandant: Gedanken, Regeln, Fakten, die der Assistent
 * und die Werkzeuge (MCP) kennen sollen. Kommt aus der App, aus Claude oder ChatGPT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wissen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // wer es gemerkt hat
            $table->string('title')->nullable();
            $table->text('body');
            $table->json('tags')->nullable();
            $table->string('source', 40)->default('app');                             // app, mcp, assistent
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wissen');
    }
};

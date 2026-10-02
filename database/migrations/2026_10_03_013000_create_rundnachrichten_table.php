<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rundnachrichten: Entwuerfe zum Weiterarbeiten und das Protokoll der verschickten (wer, wann, wie viele). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rundnachrichten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();     // wer geschrieben hat
            $table->string('status', 12)->default('entwurf');                   // entwurf | gesendet
            $table->string('an', 12)->default('alle');                          // alle | programm | einzelne
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->json('user_ids')->nullable();
            $table->string('titel', 120)->nullable();
            $table->text('text');
            $table->string('url', 500)->nullable();
            $table->json('kanaele')->nullable();
            $table->boolean('mail_alle')->default(false);
            $table->boolean('chat')->default(false);
            $table->boolean('persoenlich')->default(false);
            $table->unsignedInteger('empfaenger')->nullable();
            $table->unsignedInteger('erreicht')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rundnachrichten');
    }
};

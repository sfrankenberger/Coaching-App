<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fragen an die Coachin im Kursraum, mit Status fuer den naechsten Call. Antworten sind comments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('status', 20)->default('offen');         // offen | call | beantwortet | besprochen | zu
            $table->string('visibility', 20)->default('program');   // program (alle im Kurs) | coach (nur Coachin und Team)
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'program_id', 'status']);
            $table->index(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

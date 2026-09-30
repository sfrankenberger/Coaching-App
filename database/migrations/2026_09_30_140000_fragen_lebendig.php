<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lebendige Fragen (wie lea-kursraum-plus): Antwort auf Antwort, beste Antwort,
 * Bearbeiten, Folgen und Stummschalten, letzter Besuch je Person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('commentable_id')->constrained('comments')->nullOnDelete();
            $table->boolean('is_best')->default(false)->after('body');
            $table->timestamp('edited_at')->nullable()->after('is_best');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->timestamp('last_answer_at')->nullable()->after('answered_at');
        });

        // Je Person und Frage: folgen (1), stumm (0) oder Standard (null) und wann zuletzt gelesen
        Schema::create('question_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('folgen')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_states');
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn('last_answer_at'));
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['is_best', 'edited_at']);
        });
    }
};

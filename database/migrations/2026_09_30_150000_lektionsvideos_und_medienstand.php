<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lektionsvideos bekommen Abschrift, Zusammenfassung und Kapitel wie Material (lea-lektion-kapitel),
 * Materialvideos einen Angeschaut-Status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('vimeo_id', 40);
            $table->string('duration', 60)->nullable();
            $table->longText('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->string('prepare_status', 20)->nullable();   // wartet | bereit | ohne_abschrift | fehler
            $table->unsignedTinyInteger('prepare_tries')->default(0);
            $table->timestamps();
            $table->unique(['unit_id', 'vimeo_id']);
            $table->index(['tenant_id', 'prepare_status']);
        });

        Schema::table('media_positions', function (Blueprint $table) {
            $table->timestamp('watched_at')->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('media_positions', fn (Blueprint $table) => $table->dropColumn('watched_at'));
        Schema::dropIfExists('unit_videos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reflexion gehoert zu einer Kurswoche; Notizen mit Foto und Link (wie lea-notizen mit notiz_bild). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reflections', fn (Blueprint $table) => $table->foreignId('step_id')->nullable()->after('program_id')->constrained('program_steps')->nullOnDelete());
        Schema::table('notes', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('body');
            $table->string('image_url', 500)->nullable()->after('image_path');
            $table->string('link_url', 500)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('reflections', fn (Blueprint $table) => $table->dropConstrainedForeignId('step_id'));
        Schema::table('notes', fn (Blueprint $table) => $table->dropColumn(['image_path', 'image_url', 'link_url']));
    }
};

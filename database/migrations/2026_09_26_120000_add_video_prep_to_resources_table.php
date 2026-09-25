<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Material mit Vimeo-Video: Abschrift und Zusammenfassung wie bei den Aufzeichnungen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('vimeo_id', 30)->nullable()->after('image_url');
            $table->longText('transcript')->nullable()->after('body');
            $table->text('summary')->nullable()->after('transcript');
            $table->string('prepare_status', 20)->nullable()->after('summary');   // wartet | bereit | ohne_abschrift | fehler
            $table->unsignedSmallInteger('prepare_tries')->default(0)->after('prepare_status');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['vimeo_id', 'transcript', 'summary', 'prepare_status', 'prepare_tries']);
        });
    }
};

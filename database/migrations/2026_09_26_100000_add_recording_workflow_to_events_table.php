<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufzeichnungs-Wache (wie novamira-aufzeichnungen): Vimeo-Video am Termin, Vorschaubild,
 * Stand (wartet, gefunden, abschrift, bereit, freigegeben, nicht_gefunden, ohne_abschrift)
 * und Versuche. recording_notified_at bleibt der Zeitpunkt der Freigabe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('vimeo_id', 30)->nullable()->after('recording_duration');
            $table->string('recording_thumb', 500)->nullable()->after('vimeo_id');
            $table->string('recording_status', 20)->nullable()->after('recording_thumb');
            $table->unsignedSmallInteger('recording_tries')->default(0)->after('recording_status');
            $table->unique(['tenant_id', 'vimeo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'vimeo_id']);
            $table->dropColumn(['vimeo_id', 'recording_thumb', 'recording_status', 'recording_tries']);
        });
    }
};

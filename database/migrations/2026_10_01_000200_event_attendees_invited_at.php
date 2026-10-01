<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Weitere Personen an einem Termin (z. B. Paar-Coaching): vom Team eingeladen, sehen Termin, Kalender und Aufzeichnung wie die Hauptperson. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};

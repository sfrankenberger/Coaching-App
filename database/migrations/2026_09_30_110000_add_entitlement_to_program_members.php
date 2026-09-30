<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine Kurs-Mitgliedschaft aus einem Verkauf haengt am Zugang (entitlement). Laeuft der Zugang ab
 * oder wird er beendet, gilt die Mitgliedschaft nicht mehr. Ohne Zugang (Import, Coachin traegt ein) bleibt sie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_members', function (Blueprint $table) {
            $table->foreignId('entitlement_id')->nullable()->after('user_id')->constrained('entitlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('program_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entitlement_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wochenaufgaben wie im alten Bereich: eine Aufgabe hat eine Art (abhaken, Notiz schreiben, Reflexion schreiben,
 * Frage stellen, Aufzeichnung ansehen, Termin buchen) mit passendem Knopf, und einen Wochentag, aus dem sich
 * mit der Woche des Kurses die Faelligkeit ergibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('kind', 20)->default('haken')->after('title');   // haken | notiz | reflexion | frage | aufzeichnung | termin
            $table->unsignedTinyInteger('weekday')->nullable()->after('due_at');   // 1 Montag bis 7 Sonntag
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['kind', 'weekday']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meine Projekte (wie lea-projekte im alten Bereich): ein Vorhaben, das laenger dauert als eine Woche, gehoert der
 * Person. Notizen, Aufgaben und Reflexionen lassen sich einem Projekt zuordnen, am Projekt steht der Prozessschritt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projekte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('name', 80);
            $table->text('worum')->nullable();
            $table->string('farbe', 9)->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('schritt', 20)->nullable();          // einer der neun Prozessschritte
            $table->string('visibility', 20)->default('private');   // private | coach | program | all
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'legacy_id']);
        });
        foreach (['notes', 'tasks', 'reflections', 'journal_entries'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projekte')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['notes', 'tasks', 'reflections', 'journal_entries'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_id');
            });
        }
        Schema::dropIfExists('projekte');
    }
};

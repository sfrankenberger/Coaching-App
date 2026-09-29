<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anhaenge: an eine Notiz, Aufgabe, Reflexion oder Frage laesst sich anhaengen, worum es geht
 * (Aufgabe, Notiz, Reflexion, Termin, Aufzeichnung, Material, Lektion). Wie im alten Bereich (el_refs),
 * nur mit Art und Nummer statt einer Post-ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anhaenge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('anhang_an_type', 40);   // woran es haengt (note, task, reflection, question)
            $table->unsignedBigInteger('anhang_an_id');
            $table->string('ziel_type', 40);        // was angehaengt ist (task, note, reflection, event, resource, unit)
            $table->unsignedBigInteger('ziel_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['tenant_id']);
            $table->index(['anhang_an_type', 'anhang_an_id'], 'anhaenge_an');
            $table->unique(['anhang_an_type', 'anhang_an_id', 'ziel_type', 'ziel_id'], 'anhaenge_eindeutig');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anhaenge');
    }
};

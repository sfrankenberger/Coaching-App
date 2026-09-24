<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etappe 2: ein Kursmodell fuer alles (Hybrid, Selbstlern, 1:1, Workbook, Club),
 * Angebote mit Produktzuordnung, Zugaenge (entitlements) und Notizen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('slug');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('selfpaced');   // hybrid | selfpaced | one_on_one | workbook | club
            $table->string('pacing', 20)->default('all');       // weekly | all | none
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('color', 9)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('cover_url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_internal')->default(false);     // fertig gebaut, aber noch nicht offen
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('program_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->text('summary')->nullable();                // Einleitung von der Coachin
            $table->timestamp('unlocks_at')->nullable();        // bei Wochentaktung
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'program_id', 'position']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('program_steps')->nullOnDelete();
            $table->string('legacy_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->string('type', 20)->default('lesson');      // lesson | exercise_set | text
            $table->text('intro')->nullable();
            $table->longText('body')->nullable();               // HTML
            $table->json('videos')->nullable();                 // [{url, title}]
            $table->json('links')->nullable();                  // [{url, title}]
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_core')->default(false);         // Kernuebung im Workbook
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'program_id', 'position']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_key')->nullable();           // z. B. s01-u01-f3 aus dem Workbook
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 20)->default('text');        // text | scale | values | choice | checkbox | note | heading | hint
            $table->string('title')->nullable();
            $table->text('prompt')->nullable();
            $table->json('options')->nullable();                // Werte, Auswahl, Skalenbreite
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'unit_id', 'position']);
        });

        Schema::create('progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'unit_id']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->boolean('shared_with_coach')->default(false);
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'exercise_id']);
        });

        Schema::create('program_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_program', 20)->default('participant'); // participant | coach
            $table->string('cohort')->nullable();
            $table->string('share_mode', 20)->nullable();       // alles | einzeln (Freigabe einmal am Anfang)
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'program_id', 'user_id']);
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('title');
            $table->string('type', 20)->default('course');      // course | club | hybrid | one_on_one | free
            $table->boolean('is_free')->default(false);
            $table->unsignedSmallInteger('access_days')->nullable(); // null = unbegrenzt
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('offer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);                       // woocommerce | stripe | manual
            $table->string('external_id');
            $table->timestamps();
            $table->unique(['tenant_id', 'source', 'external_id']);
        });

        Schema::create('offer_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unique(['offer_id', 'program_id']);
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('manual');    // woocommerce | stripe | manual | import
            $table->string('source_ref')->nullable();           // Bestell- oder Abo-ID
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('active');    // active | ended | cancelled
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'source', 'source_ref']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->nullableMorphs('notable');                  // Einheit, Programm, Termin ...
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('visibility', 20)->default('private'); // private | coach | program | all
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['notes', 'entitlements', 'offer_program', 'offer_products', 'offers', 'program_members', 'answers', 'progress', 'exercises', 'units', 'program_steps', 'programs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

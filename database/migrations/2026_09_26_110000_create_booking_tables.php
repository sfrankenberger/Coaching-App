<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coaching-Buchung (wie novamira-coaching): Buchungsarten je Mandant und Buchungen.
 * Der Termin selbst bleibt ein events-Eintrag (one_on_one), damit Erinnerungen und Aufzeichnung greifen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('title');
            $table->string('event_title')->nullable();
            $table->boolean('is_open')->default(false);          // ohne Kontingent buchbar (Erstgespraech)
            $table->unsignedSmallInteger('duration')->default(60);
            $table->unsignedSmallInteger('block_minutes')->nullable();   // Dauer samt Puffer
            $table->string('block_tag', 40)->nullable();          // Zusatz im Kalenderblock, der auf diese Art eingrenzt
            $table->text('text')->nullable();
            $table->json('questions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_type_id')->nullable()->constrained()->nullOnDelete();
            $table->json('answers')->nullable();
            $table->string('google_event_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('block_ends_at')->nullable();
            $table->string('status', 20)->default('gebucht');     // gebucht | abgesagt
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_types');
    }
};

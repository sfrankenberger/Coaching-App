<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verkaeufe je Mandant: was eine Person wann zu welchem Preis gekauft hat, mit Zahlungsart,
 * Stand (offen, bezahlt, storniert) und der Rechnung in der Buchhaltung (bexio). Grundlage fuer
 * "Meine Buchungen", den Zahlungsabgleich und spaeter die Kasse mit Stripe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verkaeufe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->foreignId('entitlement_id')->nullable()->constrained('entitlements')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();   // wer verkauft hat, null = Kasse
            $table->string('title');
            $table->decimal('betrag', 10, 2)->default(0);
            $table->string('waehrung', 3)->default('CHF');
            $table->string('zahlungsart', 20)->default('rechnung');    // rechnung | bezahlt | stripe | kostenlos
            $table->string('status', 20)->default('offen');            // offen | bezahlt | storniert
            $table->string('rechnung_id')->nullable();                 // ID in der Buchhaltung
            $table->string('rechnung_nr')->nullable();
            $table->string('rechnung_link', 500)->nullable();          // online bezahlen
            $table->date('faellig_am')->nullable();
            $table->timestamp('bezahlt_am')->nullable();
            $table->string('herkunft', 120)->nullable();               // dossier, kasse, funnel-xy
            $table->text('notiz')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verkaeufe');
    }
};

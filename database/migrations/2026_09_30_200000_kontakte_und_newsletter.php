<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Etappe 11: Kontakte (Stufe unter Gast, ohne Login), Newsletter mit Versand je Empfaengerin,
| Serien (Autoresponder). Alles je Mandant.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontakte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 20)->default('angemeldet');    // angemeldet (wartet auf Bestaetigung) | bestaetigt | abgemeldet | abgeprallt
            $table->json('tags')->nullable();
            $table->json('einwilligung')->nullable();               // zeit, ip, referrer, herkunft, text
            $table->string('token', 40)->unique();                  // fuer Abmelden, Profil, Webversion
            $table->string('legacy_id')->nullable();                // Mailster-Abonnentin
            $table->timestamp('bestaetigt_at')->nullable();
            $table->timestamp('abgemeldet_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('newsletter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('betreff');
            $table->string('vorschautext')->nullable();
            $table->string('titel')->nullable();                    // Headline in der Mail
            $table->text('text')->nullable();                       // Absaetze, Zeilenumbrueche, einfache Links
            $table->string('bild_url', 500)->nullable();
            $table->string('knopf_text', 80)->nullable();
            $table->string('knopf_url', 500)->nullable();
            $table->json('tags')->nullable();                       // an wen: Kontakte mit einem dieser Tags, leer = alle bestaetigten
            $table->string('status', 20)->default('entwurf');       // entwurf | geplant | laeuft | gesendet
            $table->timestamp('geplant_at')->nullable();
            $table->timestamp('gestartet_at')->nullable();
            $table->timestamp('gesendet_at')->nullable();
            $table->unsignedInteger('empfaenger')->default(0);
            $table->unsignedInteger('gesendet')->default(0);
            $table->unsignedInteger('geoeffnet')->default(0);
            $table->unsignedInteger('geklickt')->default(0);
            $table->json('settings')->nullable();                   // testadressen, fehler
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('newsletter_versand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_id')->constrained('newsletter')->cascadeOnDelete();
            $table->foreignId('kontakt_id')->constrained('kontakte')->cascadeOnDelete();
            $table->string('token', 40)->unique();                  // Oeffnen, Klick, Webversion
            $table->string('status', 20)->default('wartet');        // wartet | gesendet | fehler
            $table->timestamp('gesendet_at')->nullable();
            $table->timestamp('geoeffnet_at')->nullable();
            $table->timestamp('geklickt_at')->nullable();
            $table->string('fehler', 500)->nullable();
            $table->timestamps();
            $table->unique(['newsletter_id', 'kontakt_id']);
            $table->index(['tenant_id', 'newsletter_id', 'status']);
        });

        Schema::create('serien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('titel');
            $table->string('tag', 80);                              // loest aus, sobald ein Kontakt den Tag bekommt
            $table->boolean('aktiv')->default(true);
            $table->json('schritte')->nullable();                   // [{tage, betreff, titel, text, bild_url, knopf_text, knopf_url}]
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'tag']);
        });

        Schema::create('serien_laeufe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('serien')->cascadeOnDelete();
            $table->foreignId('kontakt_id')->constrained('kontakte')->cascadeOnDelete();
            $table->unsignedSmallInteger('schritt')->default(0);    // naechster Schritt (Index)
            $table->timestamp('naechste_at')->nullable();
            $table->timestamp('fertig_at')->nullable();
            $table->timestamps();
            $table->unique(['serie_id', 'kontakt_id']);
            $table->index(['tenant_id', 'naechste_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serien_laeufe');
        Schema::dropIfExists('serien');
        Schema::dropIfExists('newsletter_versand');
        Schema::dropIfExists('newsletter');
        Schema::dropIfExists('kontakte');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Newsletter als Bausteine (Ueberschrift, Text, Bild, Knopf, Trenner, Zitat, Kasten, Angebot). Alte Felder bleiben als Rueckfall. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter', function (Blueprint $table) {
            $table->json('bloecke')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter', function (Blueprint $table) {
            $table->dropColumn('bloecke');
        });
    }
};

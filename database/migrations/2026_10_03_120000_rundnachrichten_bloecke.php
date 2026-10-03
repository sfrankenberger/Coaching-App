<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rundnachricht: die Mail kann aus Bausteinen bestehen (Bild, Text mit fett und Listen, Knopf), wie der Newsletter. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rundnachrichten', function (Blueprint $table) {
            $table->json('bloecke')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('rundnachrichten', function (Blueprint $table) {
            $table->dropColumn('bloecke');
        });
    }
};

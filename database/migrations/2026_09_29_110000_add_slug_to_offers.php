<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Angebote bekommen einen Kurznamen fuer den Kauflink /kaufen/{slug} und die oeffentliche Schnittstelle. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};

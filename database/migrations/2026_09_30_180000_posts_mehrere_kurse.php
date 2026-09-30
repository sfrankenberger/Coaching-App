<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Neuigkeiten koennen mehrere Kurse als Zielgruppe haben (program_id bleibt fuer den ersten). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->json('program_ids')->nullable()->after('program_id'));
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('program_ids'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Herkunft einer Buchung ("kam ueber ...") und abgesagte Termine bleiben im Kalender-Feed als abgesagt stehen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->string('herkunft', 120)->nullable()->after('answers'));
        Schema::table('events', fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable()->after('is_published'));
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('herkunft'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('cancelled_at'));
    }
};

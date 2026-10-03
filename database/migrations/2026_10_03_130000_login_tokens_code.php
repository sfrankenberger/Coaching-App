<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anmeldecode zum Link: auf dem Handy oeffnet der Link aus der Mail den Browser, nicht die App vom Home-Bildschirm
 * (iOS trennt die Cookies). Darum steht in der Mail auch ein sechsstelliger Code, den man in der App eintippt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_tokens', function (Blueprint $table) {
            $table->string('code_hash', 64)->nullable()->after('token_hash');
            $table->unsignedTinyInteger('code_versuche')->default(0)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('login_tokens', function (Blueprint $table) {
            $table->dropColumn(['code_hash', 'code_versuche']);
        });
    }
};

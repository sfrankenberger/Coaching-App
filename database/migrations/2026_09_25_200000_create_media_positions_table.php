<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wo eine Person in einem Video oder Audio stehen geblieben ist (Einheiten, Aufzeichnungen, Material). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);                    // unit-12, event-45, resource-7
            $table->unsignedInteger('seconds')->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_positions');
    }
};

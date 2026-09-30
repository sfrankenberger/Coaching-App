<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Papierkorb: Geloeschtes bleibt 90 Tage wiederherstellbar (SoftDeletes auf 24 Tabellen). */
return new class extends Migration
{
    public const TABELLEN = [
        'memberships',
        'programs',
        'program_steps',
        'units',
        'exercises',
        'questions',
        'comments',
        'events',
        'tasks',
        'notes',
        'coach_notes',
        'reflections',
        'journal_entries',
        'resources',
        'offers',
        'entitlements',
        'bookings',
        'booking_types',
        'posts',
        'podcast_episodes',
        'topics',
        'tools',
        'wissen',
        'sammlungen',
    ];

    public function up(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            Schema::table($tabelle, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};

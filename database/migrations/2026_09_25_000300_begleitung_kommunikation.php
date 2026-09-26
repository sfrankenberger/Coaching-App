<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etappe 3: Termine, Material, Aufgaben, Reflexionen, Journal, Kommentare,
 * Chat, Push-Abos, Telegram, Benachrichtigungen, Merkliste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('program_steps')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // 1:1: die Person
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('group_call');   // group_call | one_on_one | qa | webinar | reflection_day | question_day
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('zoom_url', 500)->nullable();
            $table->string('recording_url', 500)->nullable();
            $table->string('recording_duration', 60)->nullable();
            $table->longText('transcript')->nullable();
            $table->text('summary')->nullable();                  // KI-Zusammenfassung
            $table->boolean('is_published')->default(true);
            $table->timestamp('reminded_day_at')->nullable();
            $table->timestamp('reminded_hour_at')->nullable();
            $table->timestamp('recording_notified_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('invited');     // invited | declined | attended | watched
            $table->timestamp('attended_at')->nullable();
            $table->unsignedInteger('watched_seconds')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'event_id', 'user_id']);
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('title');
            $table->string('type', 20)->default('pdf');           // pdf | audio | video | link | text | image | podcast
            $table->string('url', 1000)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('description')->nullable();
            $table->string('duration', 60)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('resourceables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->morphs('resourceable');                       // program, step, unit, event, user
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['resource_id', 'resourceable_type', 'resourceable_id'], 'resourceables_unique');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();             // wessen Aufgabe
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('program_steps')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('due_at')->nullable();
            $table->string('due_time', 5)->nullable();
            $table->boolean('is_daily')->default(false);
            $table->string('source', 20)->default('manual');      // manual | coach | program | ai_summary | exercise
            $table->timestamp('done_at')->nullable();
            $table->string('visibility', 20)->default('private');  // private | coach | program | all
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('reminded_at')->nullable();
            $table->json('settings')->nullable();                  // Wochentage bei taeglichen Aufgaben
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'done_at']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('week_label')->nullable();
            $table->text('went_well')->nullable();
            $table->text('challenges')->nullable();
            $table->text('focus')->nullable();
            $table->text('addendum')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->default('entry');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('url', 1000)->nullable();
            $table->date('due_at')->nullable();
            $table->boolean('is_daily')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['user_id', 'reactable_type', 'reactable_id', 'emoji'], 'reactions_unique');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('type', 20)->default('direct');        // direct | group
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // direct: die Person (Gegenueber der Coachin)
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('audio_seconds')->nullable();
            $table->text('transcript')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->nullableMorphs('ref');                         // angehaengtes Element
            $table->string('source', 20)->default('app');         // app | telegram | system
            $table->timestamp('nudged_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'conversation_id', 'created_at']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 1000);
            $table->string('endpoint_hash', 64);
            $table->string('p256dh', 255)->nullable();
            $table->string('auth', 255)->nullable();
            $table->string('user_agent', 200)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'endpoint_hash']);
        });

        Schema::create('telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id', 40)->nullable();
            $table->string('username', 100)->nullable();
            $table->string('code', 20)->nullable();               // Verbindungscode fuer /start
            $table->boolean('active')->default(false);
            $table->timestamp('last_message_id_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'chat_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('bookmarkable');
            $table->timestamps();
            $table->unique(['user_id', 'bookmarkable_type', 'bookmarkable_id'], 'bookmarks_unique');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('digest_sent_at')->nullable()->after('settings');
            $table->timestamp('last_seen_at')->nullable()->after('digest_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', fn (Blueprint $t) => $t->dropColumn(['digest_sent_at', 'last_seen_at']));
        foreach (['bookmarks', 'notifications', 'telegram_links', 'push_subscriptions', 'messages', 'conversation_participants', 'conversations', 'reactions', 'comments', 'journal_entries', 'reflections', 'tasks', 'resourceables', 'resources', 'event_attendees', 'events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etappe 4: Impulse (Beitraege), Podcast, Themenfinder, KI-Zusammenfassungen,
 * Webhook-Protokoll fuer den Shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('impuls');            // impuls | neuigkeit
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();                     // HTML
            $table->string('image_url', 500)->nullable();
            $table->string('url', 1000)->nullable();                  // Original im Web
            $table->string('source', 20)->default('app');             // app | wordpress | feed
            $table->json('categories')->nullable();
            $table->string('visibility', 20)->default('members');     // members | team | program
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->json('notify_channels')->nullable();              // push, mail
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'published_at']);
            $table->index(['tenant_id', 'legacy_id']);
        });

        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('show');                                   // Name der Sendung
            $table->string('guid');
            $table->string('title');
            $table->string('slug');
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();                     // Shownotes (HTML)
            $table->string('audio_url', 1000)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->longText('transcript')->nullable();
            $table->json('chapters')->nullable();                     // [{start, titel}]
            $table->json('faq')->nullable();                          // [{frage, antwort}]
            $table->text('summary')->nullable();
            $table->json('keywords')->nullable();
            $table->boolean('is_published')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'guid']);
            $table->index(['tenant_id', 'published_at']);
            $table->index(['tenant_id', 'slug']);
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');                               // program, step, unit, resource, event, post, episode
            $table->timestamps();
            $table->unique(['topic_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
        });

        // Themenfinder-Text je Inhalt: worum es geht, wobei es hilft, Stichworte
        Schema::create('finder_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('profilable');
            $table->text('summary')->nullable();
            $table->text('helps')->nullable();
            $table->json('keywords')->nullable();
            $table->boolean('is_checked')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['profilable_type', 'profilable_id'], 'finder_profiles_unique');
        });

        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('summarizable');                           // event, episode
            $table->string('kind', 20)->default('summary');
            $table->longText('body')->nullable();
            $table->json('tasks')->nullable();                        // [{titel, text, fuer}]
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->string('status', 20)->default('pending');         // pending | done | failed
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 30);                             // woocommerce, stripe
            $table->string('topic', 60)->nullable();
            $table->string('external_id')->nullable();
            $table->string('status', 20)->default('ok');              // ok | ignored | error
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_logs', 'ai_summaries', 'finder_profiles', 'taggables', 'topics', 'podcast_episodes', 'posts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

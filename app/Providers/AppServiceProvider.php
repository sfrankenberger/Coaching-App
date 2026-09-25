<?php

namespace App\Providers;

use App\Models\Answer;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\JournalEntry;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Note;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Question;
use App\Models\Reflection;
use App\Models\Resourceable;
use App\Models\Task;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Observers\EventObserver;
use App\Observers\PostObserver;
use App\Observers\ProgramMemberObserver;
use App\Observers\ResourceableObserver;
use App\Observers\TaskObserver;
use App\Policies\BookingPolicy;
use App\Policies\CommentPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\EigenerEintragPolicy;
use App\Policies\EventPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\ResourcePolicy;
use App\Programs\ProgramAccess;
use App\Support\Database\MariaDbVerbindung;
use App\Support\Database\MySqlVerbindung;
use App\Support\Database\SqliteVerbindung;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Apple\Provider as AppleProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
        $this->app->singleton(Branding::class);

        // Zeitpunkte in Abfragen immer als UTC binden (Modelle liefern Ortszeit)
        Connection::resolverFor('mysql', fn ($pdo, $db, $prefix, $config) => new MySqlVerbindung($pdo, $db, $prefix, $config));
        Connection::resolverFor('mariadb', fn ($pdo, $db, $prefix, $config) => new MariaDbVerbindung($pdo, $db, $prefix, $config));
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new SqliteVerbindung($pdo, $db, $prefix, $config));
    }

    public function boot(): void
    {
        // Kurze Namen fuer polymorphe Beziehungen (notable, resourceable ...)
        Relation::enforceMorphMap([
            'program' => Program::class,
            'step' => ProgramStep::class,
            'unit' => Unit::class,
            'user' => User::class,
            'event' => Event::class,
            'resource' => \App\Models\Resource::class,
            'task' => Task::class,
            'note' => Note::class,
            'reflection' => Reflection::class,
            'journal' => JournalEntry::class,
            'message' => Message::class,
            'comment' => Comment::class,
            'answer' => Answer::class,
            'membership' => Membership::class,
            'question' => Question::class,
            'post' => Post::class,
            'episode' => PodcastEpisode::class,
            'topic' => Topic::class,
        ]);

        // Wer darf was: je Modell eine Policy, die Regeln liegen in ProgramAccess, Begleitung, Chat
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(\App\Models\Resource::class, ResourcePolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Task::class, EigenerEintragPolicy::class);
        Gate::policy(Note::class, EigenerEintragPolicy::class);
        Gate::policy(Reflection::class, EigenerEintragPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        // Wer bei was Bescheid bekommt. Listener in app/Listeners findet Laravel selbst
        // (BenachrichtigeBeiNachricht auf MessageSent), nicht zusaetzlich registrieren, sonst doppelt.
        Event::observe(EventObserver::class);
        Task::observe(TaskObserver::class);
        ProgramMember::observe(ProgramMemberObserver::class);
        Post::observe(PostObserver::class);
        Resourceable::observe(ResourceableObserver::class);

        // Apple kommt nicht mit Socialite selbst, sondern aus socialiteproviders/apple.
        $this->app->make(SocialiteFactory::class)->extend('apple', function ($app) {
            $config = $app['config']['services.apple'] ?? [];

            return $app->make(SocialiteFactory::class)->buildProvider(AppleProvider::class, $config);
        });
    }
}

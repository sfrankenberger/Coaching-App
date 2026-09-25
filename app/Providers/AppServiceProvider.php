<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\JournalEntry;
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
use App\Programs\ProgramAccess;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
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
        \Illuminate\Database\Connection::resolverFor('mysql', fn ($pdo, $db, $prefix, $config) => new \App\Support\Database\MySqlVerbindung($pdo, $db, $prefix, $config));
        \Illuminate\Database\Connection::resolverFor('mariadb', fn ($pdo, $db, $prefix, $config) => new \App\Support\Database\MariaDbVerbindung($pdo, $db, $prefix, $config));
        \Illuminate\Database\Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new \App\Support\Database\SqliteVerbindung($pdo, $db, $prefix, $config));
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
            'answer' => \App\Models\Answer::class,
            'membership' => \App\Models\Membership::class,
            'question' => Question::class,
            'post' => Post::class,
            'episode' => PodcastEpisode::class,
            'topic' => Topic::class,
        ]);

        // Zugriff auf Programme an genau einer Stelle
        Gate::define('view-program', fn (User $user, Program $program) => app(ProgramAccess::class)->canView($user, $program));

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

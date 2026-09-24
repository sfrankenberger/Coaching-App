<?php

namespace App\Providers;

use App\Events\MessageSent;
use App\Listeners\BenachrichtigeBeiNachricht;
use App\Models\Comment;
use App\Models\Event;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\Note;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Reflection;
use App\Models\Resourceable;
use App\Models\Task;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Observers\EventObserver;
use App\Observers\ResourceableObserver;
use App\Observers\TaskObserver;
use App\Programs\ProgramAccess;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event as EventFacade;
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
            'post' => Post::class,
            'episode' => PodcastEpisode::class,
            'topic' => Topic::class,
        ]);

        // Zugriff auf Programme an genau einer Stelle
        Gate::define('view-program', fn (User $user, Program $program) => app(ProgramAccess::class)->canView($user, $program));

        // Wer bei was Bescheid bekommt
        EventFacade::listen(MessageSent::class, BenachrichtigeBeiNachricht::class);
        Event::observe(EventObserver::class);
        Task::observe(TaskObserver::class);
        Resourceable::observe(ResourceableObserver::class);

        // Apple kommt nicht mit Socialite selbst, sondern aus socialiteproviders/apple.
        $this->app->make(SocialiteFactory::class)->extend('apple', function ($app) {
            $config = $app['config']['services.apple'] ?? [];

            return $app->make(SocialiteFactory::class)->buildProvider(AppleProvider::class, $config);
        });
    }
}

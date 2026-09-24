<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\Reaction;
use App\Models\Reflection;
use App\Models\Resource;
use App\Models\Resourceable;
use App\Models\Task;
use App\Models\TelegramLink;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regel 6 fuer die Tabellen aus Etappe 3.
 */
class BegleitungIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabellen_aus_etappe_3_sind_getrennt(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $user = User::factory()->create();
        $cur = app(CurrentTenant::class);

        $cur->run($a, function () use ($user) {
            $e = Event::create(['title' => 'Call', 'starts_at' => now()]);
            EventAttendee::create(['event_id' => $e->id, 'user_id' => $user->id]);
            $r = Resource::create(['title' => 'PDF', 'url' => 'https://example.com/x.pdf']);
            Resourceable::create(['resource_id' => $r->id, 'resourceable_type' => 'event', 'resourceable_id' => $e->id]);
            $t = Task::create(['user_id' => $user->id, 'title' => 'Tun']);
            Reflection::create(['user_id' => $user->id, 'went_well' => 'x']);
            JournalEntry::create(['user_id' => $user->id, 'body' => 'x']);
            Comment::create(['user_id' => $user->id, 'commentable_type' => 'task', 'commentable_id' => $t->id, 'body' => 'x']);
            Reaction::create(['user_id' => $user->id, 'reactable_type' => 'task', 'reactable_id' => $t->id, 'emoji' => 'ja']);
            $c = Conversation::create(['user_id' => $user->id]);
            ConversationParticipant::create(['conversation_id' => $c->id, 'user_id' => $user->id]);
            Message::create(['conversation_id' => $c->id, 'user_id' => $user->id, 'body' => 'hi']);
            PushSubscription::create(['user_id' => $user->id, 'endpoint' => 'https://push', 'endpoint_hash' => sha1('https://push')]);
            TelegramLink::create(['user_id' => $user->id, 'chat_id' => '1']);
            Bookmark::create(['user_id' => $user->id, 'bookmarkable_type' => 'resource', 'bookmarkable_id' => $r->id]);
        });

        foreach ([Event::class, EventAttendee::class, Resource::class, Resourceable::class, Task::class, Reflection::class, JournalEntry::class, Comment::class, Reaction::class, Conversation::class, ConversationParticipant::class, Message::class, PushSubscription::class, TelegramLink::class, Bookmark::class] as $model) {
            $this->assertSame(1, $cur->run($a, fn () => $model::count()), $model);
            $this->assertSame(0, $cur->run($b, fn () => $model::count()), $model);
            $this->assertSame(0, $model::count(), $model.' ohne Mandant');
        }
    }
}

<?php

namespace App\Observers;

use App\Models\Membership;
use App\Models\Post;
use App\Models\ProgramMember;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use Illuminate\Support\Str;

/** Ein veroeffentlichter Beitrag mit gewaehlten Kanaelen wird einmal gemeldet. */
class PostObserver
{
    public function saved(Post $post): void
    {
        if ($post->source !== 'app' || $post->notified_at || empty($post->notify_channels)) {
            return;
        }
        if (! $post->is_published || ($post->published_at && $post->published_at->isFuture())) {
            return;
        }
        $this->notify($post);
    }

    public function notify(Post $post): void
    {
        $ids = match ($post->visibility) {
            'members' => Membership::query()->where('status', 'active')->pluck('user_id'),
            'program' => $post->program_id ? ProgramMember::where('program_id', $post->program_id)->pluck('user_id') : collect(),
            default => collect(),
        };
        $ids = $ids->reject(fn ($id) => $id === $post->author_id)->values();
        $channels = (array) $post->notify_channels;

        if ($ids->isNotEmpty()) {
            app(Notifier::class)->send($ids, new Nachricht(
                titel: ($post->type === 'neuigkeit' ? 'Neu: ' : 'Impuls: ').$post->title,
                text: Str::limit($post->excerptText(240), 240),
                url: route('impulse.show', $post),
                anlass: 'impuls',
                tag: 'impuls-'.$post->id,
                mailWennKeinPush: in_array('mail', $channels, true),
                knopf: 'Lesen',
            ));
        }
        $post->forceFill(['notified_at' => now()])->saveQuietly();
    }
}

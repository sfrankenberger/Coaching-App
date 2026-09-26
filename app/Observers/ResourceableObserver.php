<?php

namespace App\Observers;

use App\Models\Resourceable;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;

/** Material direkt fuer eine Person geteilt: sie erfaehrt es. */
class ResourceableObserver
{
    public function created(Resourceable $link): void
    {
        if ($link->resourceable_type !== 'user' || ! $link->resource) {
            return;
        }
        $von = $link->shared_by ? User::find($link->shared_by)?->vorname() : null;

        app(Notifier::class)->send([$link->resourceable_id], new Nachricht(
            titel: ($von ?: 'Deine Coachin').' hat Material für dich',
            text: $link->resource->title,
            url: route('material.index'),
            anlass: 'material',
            tag: 'material-'.$link->resource_id,
            knopf: 'Zum Material',
        ));
    }
}

<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
| Private Kanaele fuer Reverb. Die Anmeldung laeuft ueber /broadcasting/auth mit der
| Web-Session, der Mandant kommt wie ueberall aus der Domain (IdentifyTenant).
*/
Broadcast::channel('gespraech.{id}', function (User $user, int $id) {
    $conv = Conversation::find($id);

    return $conv && Gate::forUser($user)->allows('view', $conv);
});

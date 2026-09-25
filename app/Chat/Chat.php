<?php

namespace App\Chat;

use App\Enums\Role;
use App\Events\MessageSent;
use App\Jobs\ConvertAudio;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Program;
use App\Models\User;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Gespraeche: 1:1 zwischen einer Person und der Coachin (samt Team),
 * Gruppe je Programm. Nachrichten mit Text, Sprachnachricht, Datei, Anhang.
 */
class Chat
{
    public function __construct(protected CurrentTenant $current, protected ProgramAccess $access) {}

    /** Wer im Mandanten die Coachin bzw. das Team ist. */
    public function teamIds(): Collection
    {
        return Membership::query()->whereIn('role', [Role::Owner->value, Role::Team->value])->where('status', 'active')->pluck('user_id');
    }

    /** Das 1:1-Gespraech einer Person mit der Coachin (wird bei Bedarf angelegt). */
    public function directFor(User $user, bool $create = true): ?Conversation
    {
        $conv = Conversation::where('type', 'direct')->where('user_id', $user->id)->first();
        if ($conv || ! $create) {
            return $conv;
        }

        $conv = Conversation::create(['type' => 'direct', 'user_id' => $user->id, 'title' => '1:1 mit '.$user->name]);
        $this->syncParticipants($conv, $this->teamIds()->push($user->id));

        return $conv;
    }

    /** Das Gruppengespraech eines Programms. */
    public function groupFor(Program $program, bool $create = true): ?Conversation
    {
        $conv = Conversation::where('type', 'group')->where('program_id', $program->id)->first();
        if ($conv || ! $create) {
            return $conv;
        }

        $conv = Conversation::create(['type' => 'group', 'program_id' => $program->id, 'title' => $program->title]);
        $this->syncParticipants($conv, $this->teamIds()->merge($program->members()->pluck('user_id')));

        return $conv;
    }

    public function syncParticipants(Conversation $conv, Collection $userIds): void
    {
        foreach ($userIds->unique() as $uid) {
            ConversationParticipant::firstOrCreate(['conversation_id' => $conv->id, 'user_id' => $uid]);
        }
    }

    public function canAccess(User $user, Conversation $conv): bool
    {
        if ($user->canManageCurrentTenant()) {
            return true;
        }
        if ($conv->isDirect()) {
            return $conv->user_id === $user->id;
        }
        if ($conv->program_id && $this->access->canView($user, $conv->program)) {
            $this->syncParticipants($conv, collect([$user->id]));

            return true;
        }

        return $conv->participants()->where('user_id', $user->id)->exists();
    }

    /** Alle Gespraeche einer Person mit Ungelesen-Zahl, neueste zuerst. */
    public function conversationsFor(User $user): Collection
    {
        $q = Conversation::query()->with(['user:id,name', 'program:id,title', 'participants']);
        if (! $user->canManageCurrentTenant()) {
            $q->whereHas('participants', fn ($p) => $p->where('user_id', $user->id));
        }

        return $q->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->each(fn (Conversation $c) => $c->setAttribute('ungelesen', $c->unreadCountFor($user)));
    }

    public function unreadFor(User $user): int
    {
        return $this->conversationsFor($user)->sum('ungelesen');
    }

    public function send(Conversation $conv, User $from, array $data): Message
    {
        $tenantId = $this->current->id();
        $dir = "tenants/{$tenantId}/chat/{$conv->id}";

        $msg = new Message([
            'conversation_id' => $conv->id,
            'user_id' => $from->id,
            'body' => filled($data['body'] ?? null) ? trim($data['body']) : null,
            'source' => $data['source'] ?? 'app',
        ]);

        if (($ref = $data['ref'] ?? null) && is_array($ref) && ! empty($ref['type']) && ! empty($ref['id'])) {
            $msg->ref_type = $ref['type'];
            $msg->ref_id = (int) $ref['id'];
        }
        if (($file = $data['file'] ?? null) instanceof UploadedFile) {
            $msg->attachment_path = $file->store($dir);
            $msg->attachment_name = $file->getClientOriginalName();
        }
        if (($audio = $data['audio'] ?? null) instanceof UploadedFile) {
            $ext = match (true) {
                str_contains((string) $audio->getMimeType(), 'mp4') || str_contains((string) $audio->getMimeType(), 'm4a') => 'm4a',
                str_contains((string) $audio->getMimeType(), 'ogg') => 'ogg',
                default => 'webm',
            };
            $msg->audio_path = $audio->storeAs($dir, uniqid('sprache-').'.'.$ext);
            $msg->audio_seconds = isset($data['sek']) ? max(0, min(7200, (int) $data['sek'])) : null;
            $msg->transcript = filled($data['transkript'] ?? null) ? trim($data['transkript']) : null;
        }
        $msg->save();
        if (ConvertAudio::noetig($msg->audio_path) && config('services.ffmpeg.enabled', true)) {
            ConvertAudio::dispatch((int) $msg->tenant_id, $msg->id);
        }

        $conv->forceFill(['last_message_at' => now()])->save();
        $this->markRead($conv, $from);

        MessageSent::dispatch($msg);

        return $msg;
    }

    public function markRead(Conversation $conv, User $user): void
    {
        ConversationParticipant::updateOrCreate(['conversation_id' => $conv->id, 'user_id' => $user->id], ['last_read_at' => now()]);
    }

    /** Gegenueber im 1:1 hat bis wann gelesen? (fuer den Haken an eigenen Nachrichten) */
    public function readUntilByOthers(Conversation $conv, User $user): ?Carbon
    {
        return $conv->participants->where('user_id', '!=', $user->id)->max('last_read_at');
    }

    public function deleteFiles(Message $msg): void
    {
        foreach (array_filter([$msg->audio_path, $msg->attachment_path]) as $p) {
            Storage::delete($p);
        }
    }
}

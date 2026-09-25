<?php

namespace App\Filament\Coach\Widgets;

use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Answer;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Reflection;
use App\Models\Task;
use Filament\Widgets\Widget;

/** Was in den letzten sieben Tagen von den Personen kam, und was ansteht. */
class NeuesWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected string $view = 'filament.coach.widgets.neues';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $seit = now()->subDays(7);
        $teamIds = Membership::whereIn('role', ['owner', 'team'])->pluck('user_id');
        $dossier = fn ($userId) => ($m = Membership::where('user_id', $userId)->first()) ? MembershipResource::getUrl('dossier', ['record' => $m]) : null;
        $zeilen = collect();

        foreach (Answer::where('shared_with_coach', true)->where('updated_at', '>', $seit)->with(['user:id,name', 'exercise.unit:id,title'])->latest('updated_at')->limit(15)->get() as $a) {
            if ($a->isFilled()) {
                $zeilen->push(['team' => $teamIds->contains($a->user_id), 'zeit' => $a->updated_at, 'wer' => $a->user?->name, 'was' => 'hat eine Antwort geteilt', 'detail' => $a->exercise?->unit?->title, 'url' => $dossier($a->user_id)]);
            }
        }
        foreach (Reflection::where('visibility', '!=', 'private')->where('shared_at', '>', $seit)->with('user:id,name')->latest('shared_at')->limit(15)->get() as $r) {
            $zeilen->push(['team' => $teamIds->contains($r->user_id), 'zeit' => $r->shared_at, 'wer' => $r->user?->name, 'was' => 'hat eine Reflexion geteilt', 'detail' => $r->week_label, 'url' => $dossier($r->user_id)]);
        }
        foreach (Task::whereNotNull('assigned_by')->where('done_at', '>', $seit)->with('user:id,name')->latest('done_at')->limit(15)->get() as $t) {
            $zeilen->push(['team' => $teamIds->contains($t->user_id), 'zeit' => $t->done_at, 'wer' => $t->user?->name, 'was' => 'hat erledigt', 'detail' => $t->title, 'url' => $dossier($t->user_id)]);
        }
        foreach (EventAttendee::where('status', 'declined')->where('updated_at', '>', $seit)->with(['user:id,name', 'event:id,title,starts_at'])->latest('updated_at')->limit(15)->get() as $a) {
            $zeilen->push(['team' => $teamIds->contains($a->user_id), 'zeit' => $a->updated_at, 'wer' => $a->user?->name, 'was' => 'hat abgesagt', 'detail' => $a->event?->title, 'url' => $dossier($a->user_id)]);
        }
        foreach (Message::whereNotIn('user_id', $teamIds)->where('created_at', '>', $seit)->with(['user:id,name', 'conversation'])->latest()->limit(15)->get() as $m) {
            $zeilen->push(['zeit' => $m->created_at, 'wer' => $m->user?->name, 'was' => 'hat geschrieben', 'detail' => $m->excerpt(80), 'url' => $m->conversation ? route('gespraech.show', $m->conversation) : null]);
        }

        return [
            'zeilen' => $zeilen->reject(fn ($z) => $z['team'] ?? false)->sortByDesc('zeit')->take(25)->values(),
            'termine' => Event::query()->where('is_published', true)->upcoming()->where('starts_at', '<', now()->addDays(14))->with(['program:id,title', 'user:id,name'])->limit(8)->get(),
        ];
    }
}

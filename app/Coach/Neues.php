<?php

namespace App\Coach;

use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Answer;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Reflection;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Was in den letzten Tagen von den Personen kam (geteilt, erledigt, abgesagt, geschrieben), und was ansteht. */
class Neues
{
    /** @return Collection<int, array{zeit: CarbonInterface, wer: ?string, was: string, detail: ?string, url: ?string, art: string}> */
    public function zeilen(int $tage = 7, int $max = 25): Collection
    {
        $seit = now()->subDays($tage);
        $teamIds = Membership::whereIn('role', ['owner', 'team'])->pluck('user_id');
        $dossier = fn ($userId) => ($m = Membership::where('user_id', $userId)->first()) ? MembershipResource::getUrl('dossier', ['record' => $m]) : null;
        $zeilen = collect();

        // Antworten je Person und Einheit buendeln, sonst steht dieselbe Uebung sechsmal da
        $antworten = Answer::where('shared_with_coach', true)->where('updated_at', '>', $seit)->with(['user:id,name', 'exercise.unit:id,title'])->latest('updated_at')->limit(60)->get()
            ->filter(fn (Answer $a) => $a->isFilled())->groupBy(fn (Answer $a) => $a->user_id.'-'.($a->exercise?->unit_id ?? 0));
        foreach ($antworten as $gruppe) {
            $a = $gruppe->first();
            $n = $gruppe->count();
            $zeilen->push(['art' => 'antwort', 'team' => $teamIds->contains($a->user_id), 'zeit' => $a->updated_at, 'wer' => $a->user?->name, 'was' => $n === 1 ? 'hat eine Antwort geteilt' : "hat {$n} Antworten geteilt", 'detail' => $a->exercise?->unit?->title, 'url' => $dossier($a->user_id)]);
        }
        foreach (Reflection::where('visibility', '!=', 'private')->where('shared_at', '>', $seit)->with('user:id,name')->latest('shared_at')->limit(15)->get() as $r) {
            $zeilen->push(['art' => 'reflexion', 'team' => $teamIds->contains($r->user_id), 'zeit' => $r->shared_at, 'wer' => $r->user?->name, 'was' => 'hat eine Reflexion geteilt', 'detail' => $r->week_label, 'url' => $dossier($r->user_id)]);
        }
        foreach (Task::whereNotNull('assigned_by')->where('done_at', '>', $seit)->with('user:id,name')->latest('done_at')->limit(15)->get() as $t) {
            $zeilen->push(['art' => 'aufgabe', 'team' => $teamIds->contains($t->user_id), 'zeit' => $t->done_at, 'wer' => $t->user?->name, 'was' => 'hat erledigt', 'detail' => $t->title, 'url' => $dossier($t->user_id)]);
        }
        foreach (EventAttendee::where('status', 'declined')->where('updated_at', '>', $seit)->with(['user:id,name', 'event:id,title,starts_at'])->latest('updated_at')->limit(15)->get() as $a) {
            $zeilen->push(['art' => 'absage', 'team' => $teamIds->contains($a->user_id), 'zeit' => $a->updated_at, 'wer' => $a->user?->name, 'was' => 'hat abgesagt', 'detail' => $a->event?->title, 'url' => $dossier($a->user_id)]);
        }
        foreach (Message::whereNotIn('user_id', $teamIds)->where('created_at', '>', $seit)->with(['user:id,name', 'conversation'])->latest()->limit(15)->get() as $m) {
            $zeilen->push(['art' => 'nachricht', 'team' => false, 'zeit' => $m->created_at, 'wer' => $m->user?->name, 'was' => 'hat geschrieben', 'detail' => $m->excerpt(80), 'url' => $m->conversation ? route('gespraech.show', $m->conversation) : null]);
        }

        return $zeilen->reject(fn ($z) => $z['team'] ?? false)->sortByDesc('zeit')->take($max)->values();
    }

    public function termine(int $tage = 14, int $max = 8): Collection
    {
        return Event::query()->where('is_published', true)->upcoming()->where('starts_at', '<', now()->addDays($tage))->with(['program:id,title', 'user:id,name'])->limit($max)->get();
    }
}

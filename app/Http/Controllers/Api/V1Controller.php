<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KursResource;
use App\Http\Resources\TerminResource;
use App\Models\Task;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use App\Tenancy\Branding;
use Illuminate\Http\Request;

/** Lesende JSON-API: was die Person sieht, im Mandanten der Domain. */
class V1Controller extends Controller
{
    public function __construct(protected ProgramAccess $access, protected Begleitung $begleitung, protected ProgressTracker $progress) {}

    public function ich(Request $request): array
    {
        $u = $request->user();
        $m = $u->membershipIn();
        abort_unless($m && $m->isActive(), 403);

        return [
            'id' => $u->id, 'name' => $u->name, 'vorname' => $u->vorname(), 'email' => $u->email,
            'rolle' => $m->role->value, 'app' => app(Branding::class)->appName(), 'coach' => app(Branding::class)->coachName(),
            'mitteilungen_ungelesen' => $u->notifications()->whereNull('read_at')->count(),
        ];
    }

    public function kurse(Request $request)
    {
        $u = $request->user();

        return KursResource::collection($this->access->programsFor($u)->map(fn ($p) => $p->setAttribute('stand', $this->progress->summary($u, $p))));
    }

    public function termine(Request $request)
    {
        return TerminResource::collection($this->begleitung->eventsQuery($request->user())->where('starts_at', '>=', now()->subDays(30))->orderBy('starts_at')->limit(100)->get());
    }

    public function mitteilungen(Request $request): array
    {
        return $request->user()->notifications()->limit(50)->get()
            ->map(fn ($m) => ['id' => $m->id, 'titel' => $m->titel(), 'text' => $m->text(), 'url' => $m->url(), 'gelesen' => (bool) $m->read_at, 'zeit' => $m->created_at->toIso8601String()])->all();
    }

    public function aufgaben(Request $request): array
    {
        return Task::where('user_id', $request->user()->id)->open()->orderBy('due_at')->limit(100)->get()
            ->map(fn (Task $t) => ['id' => $t->id, 'titel' => $t->title, 'text' => $t->body, 'bis' => $t->due_at?->toDateString(), 'taeglich' => (bool) $t->is_daily])->all();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Membership;
use App\Programs\Begleitung;
use App\Support\Ics;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Kalender-Abo (ohne Anmeldung, ueber den Schluessel der Person) und Datei je Termin. */
class KalenderController extends Controller
{
    public function __construct(protected Begleitung $begleitung, protected CurrentTenant $current, protected Branding $branding) {}

    public function abo(Request $request, string $token): Response
    {
        $m = Membership::query()->where('status', 'active')->where('settings->calendar_token', $token)->first();
        abort_unless($m && strlen($token) >= 32, 404);
        $user = $m->user;
        $events = $this->begleitung->eventsQuery($user)->where('starts_at', '>=', now()->subMonths(3))->orderBy('starts_at')->limit(500)->get();

        return $this->ics(Ics::calendar($events, $this->branding->appName(), $this->current->getOrFail()->timezone ?: config('app.timezone'), $request->getHost()), 'termine.ics');
    }

    public function termin(Request $request, Event $termin): Response
    {
        Gate::authorize('view', $termin);

        return $this->ics(Ics::calendar(collect([$termin]), $this->branding->appName(), $this->current->getOrFail()->timezone ?: config('app.timezone'), $request->getHost()), 'termin-'.$termin->id.'.ics');
    }

    protected function ics(string $body, string $name): Response
    {
        return response($body, 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'no-cache']);
    }
}

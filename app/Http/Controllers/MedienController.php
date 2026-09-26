<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\MediaPosition;
use App\Models\Resource;
use App\Models\Unit;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merkt sich, wo jemand in einem Video oder Audio steht. Ab 80 Prozent gilt eine
 * Einheit als erledigt bzw. eine Aufzeichnung als gesehen (wie im alten Bereich).
 */
class MedienController extends Controller
{
    public const GESEHEN_AB = 0.8;

    public function __construct(protected Begleitung $begleitung, protected ProgramAccess $access, protected ProgressTracker $progress) {}

    public function position(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'regex:/^(unit|event|resource)-\d+$/'],
            'seconds' => ['required', 'numeric', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);
        $user = $request->user();
        [$art, $id] = explode('-', $data['key']);
        $ziel = match ($art) {
            'unit' => Unit::with('program')->find($id),
            'event' => Event::find($id),
            'resource' => Resource::find($id),
        };
        $darf = $ziel && match ($art) {
            'unit' => $ziel->program && $this->access->canView($user, $ziel->program),
            'event' => $this->begleitung->canViewEvent($user, $ziel),
            'resource' => $this->begleitung->canViewResource($user, $ziel),
        };
        abort_unless($darf, 403);

        $pos = MediaPosition::updateOrCreate(
            ['user_id' => $user->id, 'key' => $data['key']],
            ['seconds' => (int) $data['seconds'], 'duration' => isset($data['duration']) ? (int) $data['duration'] : null],
        );

        $erledigt = false;
        if ($pos->duration && $pos->seconds >= $pos->duration * self::GESEHEN_AB) {
            if ($art === 'unit' && ! $this->progress->completedUnitIds($user, $ziel->program)->contains($ziel->id)) {
                $this->progress->toggle($user, $ziel, true);
                $erledigt = true;
            }
            if ($art === 'event') {
                $row = EventAttendee::firstOrNew(['event_id' => $ziel->id, 'user_id' => $user->id]);
                if (! in_array($row->status, ['attended', 'watched'], true)) {
                    $row->forceFill(['status' => 'watched', 'attended_at' => now()])->save();
                    $erledigt = true;
                }
            }
        }

        return response()->json(['ok' => true, 'erledigt' => $erledigt]);
    }
}

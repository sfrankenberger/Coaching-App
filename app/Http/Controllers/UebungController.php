<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertAnswerAudio;
use App\Jobs\ConvertAudio;
use App\Models\Answer;
use App\Models\Exercise;
use App\Models\ProgramMember;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Besondere Uebungsteile im Arbeitsbuch: Aufnahme und taegliche Praxis. */
class UebungController extends Controller
{
    /** Aufnahme speichern (privat unter storage/app/tenants/{id}/answers/{user}). */
    public function aufnahme(Request $request): JsonResponse
    {
        $data = $request->validate(['exercise_id' => ['required', 'integer'], 'ton' => ['required', 'file', 'max:40960']]);
        $ex = Exercise::with('unit.program')->findOrFail($data['exercise_id']);
        Gate::authorize('view', $ex->unit->program);
        abort_unless($ex->type === 'audio', 422);
        $user = $request->user();
        $datei = $request->file('ton');
        $ext = str_contains((string) $datei->getMimeType(), 'mp4') ? 'm4a' : (str_contains((string) $datei->getMimeType(), 'ogg') ? 'ogg' : 'webm');
        $pfad = $datei->storeAs('tenants/'.$ex->tenant_id.'/answers/'.$user->id, 'aufnahme-'.$ex->id.'-'.time().'.'.$ext);

        $answer = Answer::firstOrNew(['user_id' => $user->id, 'exercise_id' => $ex->id]);
        $alt = $answer->value['v'] ?? null;
        if (! $answer->exists && ProgramMember::where('program_id', $ex->unit->program_id)->where('user_id', $user->id)->value('share_mode') === 'alles') {
            $answer->forceFill(['shared_with_coach' => true, 'shared_at' => now()]);
        }
        $answer->value = ['v' => $pfad];
        $answer->save();
        if (is_string($alt) && $alt !== $pfad) {
            Storage::delete($alt);
        }
        if (ConvertAudio::noetig($pfad) && config('services.ffmpeg.enabled', true)) {
            ConvertAnswerAudio::dispatch((int) $ex->tenant_id, $answer->id);
        }

        return response()->json(['ok' => true, 'url' => route('uebung.aufnahme.hoeren', $answer)]);
    }

    public function hoeren(Request $request, Answer $antwort): StreamedResponse
    {
        $user = $request->user();
        abort_unless($antwort->user_id === $user->id || ($user->canManageCurrentTenant() && $antwort->shared_with_coach), 403);
        $pfad = $antwort->value['v'] ?? null;
        abort_unless(is_string($pfad) && Storage::exists($pfad), 404);

        return Storage::response($pfad);
    }

    /** Taegliche Praxis starten: Startdatum merken und eine taegliche Aufgabe anlegen. */
    public function praxis(Request $request, Exercise $uebung): RedirectResponse
    {
        $uebung->load('unit.program');
        Gate::authorize('view', $uebung->unit->program);
        abort_unless($uebung->type === 'practice', 422);
        $user = $request->user();

        $answer = Answer::firstOrNew(['user_id' => $user->id, 'exercise_id' => $uebung->id]);
        if (! $answer->exists) {
            $answer->value = ['v' => now()->toDateString()];
            $answer->save();
            $tage = (int) ($uebung->options['tage'] ?? 21);
            Task::create([
                'user_id' => $user->id, 'program_id' => $uebung->unit->program_id, 'unit_id' => $uebung->unit_id, 'source' => 'exercise',
                'title' => $uebung->options['aufgabe'] ?? ($uebung->prompt ?: 'Tägliche Praxis'),
                'is_daily' => true, 'due_at' => now()->addDays($tage - 1)->toDateString(), 'visibility' => 'private',
            ]);
        }

        return back()->with('meldung', 'Deine Praxis läuft. Die tägliche Aufgabe steht in deinem Journal.');
    }
}

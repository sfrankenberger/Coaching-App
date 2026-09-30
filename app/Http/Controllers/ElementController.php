<?php

namespace App\Http\Controllers;

use App\Coach\Geteilt;
use App\Models\Note;
use App\Models\Program;
use App\Models\Projekt;
use App\Models\Reflection;
use App\Programs\ProgramAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Schnelle Handgriffe aus dem Dreipunkt-Menue: anpinnen, teilen mit, einem Projekt zuordnen. */
class ElementController extends Controller
{
    public function schnell(Request $request, Geteilt $geteilt, ProgramAccess $access, string $typ, int $id): RedirectResponse
    {
        $user = $request->user();
        $item = $geteilt->finden($typ, $id);
        abort_unless($item && (int) $item->user_id === $user->id, 404);
        $was = (string) $request->input('was');

        if ($was === 'pin' && ! $item instanceof Reflection) {
            $item->forceFill(['is_pinned' => ! $item->is_pinned])->save();

            return back()->with('meldung', $item->is_pinned ? 'Angepinnt.' : 'Nicht mehr angepinnt.');
        }
        if ($was === 'projekt') {
            $pid = Projekt::where('user_id', $user->id)->whereKey((int) $request->input('projekt', 0))->value('id');
            $item->forceFill(['project_id' => $pid])->save();

            return back()->with('meldung', $pid ? 'Dem Projekt zugeordnet.' : 'Ohne Projekt.');
        }
        if ($was === 'teilen') {
            $sicht = (string) $request->input('sicht', 'private');
            abort_unless(in_array($sicht, ['private', 'coach', 'program', 'all'], true), 422);
            $programId = null;
            if ($sicht === 'program') {
                $p = Program::find((int) $request->input('kurs', 0));
                $programId = $p && $p->gemeinschaft() && $access->canView($user, $p) ? $p->id : null;
                if (! $programId) {
                    $sicht = 'coach';
                }
            }
            $vorher = $item->visibility;
            $daten = ['visibility' => $sicht];
            if ($programId) {
                $daten['program_id'] = $programId;
            }
            if ($item instanceof Reflection) {
                $daten['shared_at'] = $sicht !== 'private' ? ($item->shared_at ?? now()) : null;
            }
            $item->forceFill($daten)->save();
            if ($vorher === 'private' && $sicht !== 'private') {
                $geteilt->melden($user, $item);
            }

            return back()->with('meldung', 'Sichtbarkeit: '.Note::sichtbarkeitText($sicht).'.');
        }

        abort(422);
    }
}

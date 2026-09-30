<?php

namespace App\Http\Controllers;

use App\Coach\Geteilt;
use App\Models\Reaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Die vier Reaktionen an geteilten Eintraegen (Notiz, Aufgabe, Reflexion), an und wieder aus. */
class ReaktionController extends Controller
{
    public function toggle(Request $request, Geteilt $geteilt, string $typ, int $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $item = $geteilt->finden($typ, $id);
        abort_unless($item && $geteilt->darfSehen($user, $item), 404);
        abort_if((int) $item->user_id === $user->id, 422, 'Auf Eigenes reagiert man nicht.');
        $emoji = (string) $request->input('emoji');
        abort_unless(array_key_exists($emoji, Reaction::EMOJIS), 422);

        $r = Reaction::where('user_id', $user->id)->where('reactable_type', $typ)->where('reactable_id', $item->getKey())->where('emoji', $emoji)->first();
        $r ? $r->delete() : Reaction::create(['user_id' => $user->id, 'reactable_type' => $typ, 'reactable_id' => $item->getKey(), 'emoji' => $emoji]);
        $item->unsetRelation('reactions');

        if ($request->expectsJson()) {
            return response()->json(['html' => view('components.reaktionen', ['item' => $item, 'typ' => $typ])->render()]);
        }

        return back();
    }
}

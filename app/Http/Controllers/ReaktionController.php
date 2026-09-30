<?php

namespace App\Http\Controllers;

use App\Coach\Geteilt;
use App\Models\Comment;
use App\Models\Question;
use App\Models\Reaction;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Reaktionen an geteilten Eintraegen (Notiz, Aufgabe, Reflexion, Projekt), an Fragen
 * (Sehe ich auch so, Die Frage habe ich auch, Das bewegt mich) und das Herz an einer Antwort.
 * Die Autorin erfaehrt von der ersten Reaktion.
 */
class ReaktionController extends Controller
{
    public function toggle(Request $request, Geteilt $geteilt, Notifier $notifier, string $typ, int $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        [$item, $erlaubt, $frage] = $this->finden($geteilt, $typ, $id);
        abort_unless($item, 404);
        abort_unless($frage ? Gate::forUser($user)->allows('view', $frage) : $geteilt->darfSehen($user, $item), 404);
        abort_if((int) $item->user_id === $user->id, 422, 'Auf Eigenes reagiert man nicht.');
        $emoji = (string) $request->input('emoji');
        abort_unless(array_key_exists($emoji, Reaction::EMOJIS) && in_array($emoji, $erlaubt, true), 422);

        $r = Reaction::where('user_id', $user->id)->where('reactable_type', $typ)->where('reactable_id', $item->getKey())->where('emoji', $emoji)->first();
        if ($r) {
            $r->delete();
        } else {
            Reaction::create(['user_id' => $user->id, 'reactable_type' => $typ, 'reactable_id' => $item->getKey(), 'emoji' => $emoji]);
            if ($frage) {
                $notifier->send([$item->user_id], new Nachricht(
                    titel: $user->vorname().' '.Reaction::EMOJIS[$emoji][0].($typ === 'comment' ? ' auf deine Antwort' : ' auf deine Frage'),
                    text: Str::limit($frage->title, 100),
                    url: route('fragen.show', $frage).($typ === 'comment' ? '#antwort-'.$item->getKey() : ''),
                    anlass: 'reaktion',
                    tag: 'reaktion-'.$typ.'-'.$item->getKey(),
                    mailWennKeinPush: false,
                ));
            }
        }
        $item->unsetRelation('reactions');

        if ($request->expectsJson()) {
            return response()->json(['html' => view('components.reaktionen', ['item' => $item, 'typ' => $typ, 'nur' => $erlaubt === array_keys(Reaction::EMOJIS) ? null : $erlaubt])->render()]);
        }

        return back();
    }

    /** [Element, erlaubte Reaktionen, zugehoerige Frage] */
    protected function finden(Geteilt $geteilt, string $typ, int $id): array
    {
        if ($typ === 'question') {
            $f = Question::find($id);

            return [$f, Question::REAKTIONEN, $f];
        }
        if ($typ === 'comment') {
            $c = Comment::where('commentable_type', 'question')->find($id);

            return [$c, ['herz'], $c?->commentable];
        }

        return [$geteilt->finden($typ, $id), array_keys(Reaction::EMOJIS), null];
    }
}

<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Chat\Terminvorschlag;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Program;
use App\Models\Reaction;
use App\Tenancy\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GespraechController extends Controller
{
    public function __construct(protected Chat $chat) {}

    /** Teilnehmerin: direkt ins 1:1. Coachin und Team: Liste aller Gespraeche. */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->canManageCurrentTenant()) {
            return redirect()->route('gespraech.show', array_filter([$this->chat->directFor($user), 'entwurf' => $request->query('entwurf')]));
        }

        return view('gespraech.index', ['gespraeche' => $this->chat->conversationsFor($user)]);
    }

    /** Gruppenaustausch eines Programms. */
    public function gruppe(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('view-program', $program);

        return redirect()->route('gespraech.show', $this->chat->groupFor($program));
    }

    public function show(Request $request, Conversation $gespraech): View
    {
        $user = $request->user();
        abort_unless($this->chat->canAccess($user, $gespraech), 403);
        $gespraech->load(['participants.user:id,name', 'user:id,name', 'program:id,title,slug']);

        $alle = (bool) $request->query('alle');
        $q = $gespraech->messages()->with(['user:id,name', 'reactions', 'ref']);
        $total = $gespraech->messages()->count();
        $messages = $alle || $total <= 30 ? $q->get() : $q->skip($total - 30)->take(30)->get();

        $this->chat->markRead($gespraech, $user);

        return view('gespraech.show', [
            'conv' => $gespraech,
            'messages' => $messages,
            'versteckt' => $alle ? 0 : max(0, $total - 30),
            'gelesenBis' => $this->chat->readUntilByOthers($gespraech, $user),
            'gegenueber' => $this->gegenueber($gespraech, $user),
            'darfSprache' => true,
        ]);
    }

    public function senden(Request $request, Conversation $gespraech): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->chat->canAccess($user, $gespraech), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,mp3,m4a,docx,txt'],
            'audio' => ['nullable', 'file', 'max:30720'],
            'sek' => ['nullable', 'integer'],
            'transkript' => ['nullable', 'string', 'max:10000'],
            'ref_type' => ['nullable', 'in:task,note,reflection,event,resource,unit'],
            'ref_id' => ['nullable', 'integer'],
        ]);

        if (blank($data['body'] ?? null) && ! $request->hasFile('file') && ! $request->hasFile('audio') && empty($data['ref_id'])) {
            return $request->expectsJson() ? response()->json(['fehler' => 'Schreib etwas.'], 422) : back()->with('fehler', 'Schreib etwas.');
        }

        $msg = $this->chat->send($gespraech, $user, [
            'body' => $data['body'] ?? null,
            'file' => $request->file('file'),
            'audio' => $request->file('audio'),
            'sek' => $data['sek'] ?? null,
            'transkript' => $data['transkript'] ?? null,
            'ref' => ! empty($data['ref_id']) ? ['type' => $data['ref_type'], 'id' => $data['ref_id']] : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $msg->id, 'html' => $this->render($gespraech, $msg->load(['user:id,name', 'reactions', 'ref']), $user)]);
        }

        return redirect()->route('gespraech.show', $gespraech)->withFragment('nachricht-'.$msg->id);
    }

    /** Polling: neue Nachrichten seit id, plus Lesestand. */
    public function neu(Request $request, Conversation $gespraech): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->chat->canAccess($user, $gespraech), 403);
        $seit = (int) $request->query('seit', 0);

        $neue = $gespraech->messages()->where('id', '>', $seit)->with(['user:id,name', 'reactions', 'ref'])->get();
        if ($neue->isNotEmpty()) {
            $this->chat->markRead($gespraech, $user);
        }
        $gespraech->load('participants');

        return response()->json([
            'letzte' => $neue->last()?->id ?? $seit,
            'html' => $neue->map(fn (Message $m) => $this->render($gespraech, $m, $user))->implode(''),
            'gelesen_bis' => $this->chat->readUntilByOthers($gespraech, $user)?->toIso8601String(),
        ]);
    }

    public function gelesen(Request $request, Conversation $gespraech): JsonResponse
    {
        abort_unless($this->chat->canAccess($request->user(), $gespraech), 403);
        $this->chat->markRead($gespraech, $request->user());

        return response()->json(['ok' => true]);
    }

    /** Reaktion auf eine fremde Nachricht setzen oder wieder nehmen. */
    /** Vorgeschlagene Zeit antippen: Termin anlegen, Bestaetigung ins Gespraech. */
    public function termin(Request $request, Message $nachricht, Terminvorschlag $vorschlag): RedirectResponse
    {
        $data = $request->validate(['i' => ['required', 'integer', 'min:0', 'max:20']]);
        $event = $vorschlag->waehlen($nachricht, $request->user(), (int) $data['i']);

        return redirect()->route('gespraech.show', $nachricht->conversation_id)
            ->with('meldung', 'Gebucht: '.$event->starts_at->translatedFormat('l, j. F, H:i').' Uhr. Du findest den Termin unter Termine.');
    }

    public function reaktion(Request $request, Message $nachricht): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $conv = $nachricht->conversation;
        abort_unless($conv && $this->chat->canAccess($user, $conv), 403);
        abort_if($nachricht->user_id === $user->id, 422);
        $emoji = (string) $request->input('emoji');
        abort_unless(array_key_exists($emoji, Reaction::EMOJIS), 422);

        $r = Reaction::where('user_id', $user->id)->where('reactable_type', 'message')->where('reactable_id', $nachricht->id)->where('emoji', $emoji)->first();
        if ($r) {
            $r->delete();
        } else {
            Reaction::create(['user_id' => $user->id, 'reactable_type' => 'message', 'reactable_id' => $nachricht->id, 'emoji' => $emoji]);
        }

        if ($request->expectsJson()) {
            return response()->json(['html' => view('gespraech._reaktionen', ['m' => $nachricht->load('reactions'), 'eigene' => false])->render()]);
        }

        return back();
    }

    /** Datei oder Sprachnachricht ausliefern (nur fuer Beteiligte). */
    public function datei(Request $request, Message $nachricht, string $art): StreamedResponse
    {
        $conv = $nachricht->conversation;
        abort_unless($conv && $this->chat->canAccess($request->user(), $conv), 403);
        $path = $art === 'audio' ? $nachricht->audio_path : $nachricht->attachment_path;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path, $art === 'audio' ? basename($path) : ($nachricht->attachment_name ?: basename($path)));
    }

    protected function render(Conversation $conv, Message $m, $user): string
    {
        return view('gespraech._nachricht', ['m' => $m, 'conv' => $conv, 'gelesenBis' => $this->chat->readUntilByOthers($conv, $user)])->render();
    }

    protected function gegenueber(Conversation $conv, $user): string
    {
        if ($conv->isDirect()) {
            if ($conv->user_id === $user->id) {
                // Die Coachin, nicht das erste Teammitglied
                return app(Branding::class)->coachName($conv->participants->firstWhere('user_id', '!=', $user->id)?->user?->vorname());
            }

            return $conv->user?->vorname() ?: 'die Person';
        }

        return $conv->program?->title ?: 'die Gruppe';
    }
}

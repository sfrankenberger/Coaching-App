<?php

namespace App\Http\Controllers;

use App\Booking\GoogleCalendar;
use App\Chat\Chat;
use App\Chat\Terminvorschlag;
use App\Coach\Ansicht;
use App\Coach\Lage;
use App\Http\Requests\NachrichtRequest;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Message;
use App\Models\Program;
use App\Models\Reaction;
use App\Models\User;
use App\Support\Anhaenge;
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
        // Teilnehmerin, oder Team in der Teilnehmer-Ansicht: das eigene 1:1, nicht die Liste aller Gespraeche
        if (! $user->canManageCurrentTenant() || Ansicht::wieTeilnehmerin($user)) {
            return redirect()->route('gespraech.show', array_filter([$this->chat->directFor($user), 'entwurf' => $request->query('entwurf'), 'ref' => $request->query('ref')]));
        }

        return view('gespraech.index', ['gespraeche' => $this->chat->conversationsFor($user)]);
    }

    /** Gruppenaustausch eines Programms. */
    public function gruppe(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('view', $program);

        return redirect()->route('gespraech.show', $this->chat->groupFor($program));
    }

    public function show(Request $request, Conversation $gespraech): View
    {
        $user = $request->user();
        Gate::authorize('view', $gespraech);
        $gespraech->load(['participants.user:id,name,avatar_path,updated_at', 'user:id,name,avatar_path,updated_at', 'program:id,title,slug']);

        $alle = (bool) $request->query('alle');
        $q = $gespraech->messages()->with(['user:id,name,avatar_path,updated_at', 'reactions', 'ref']);
        $total = $gespraech->messages()->count();
        $messages = $alle || $total <= 30 ? $q->get() : $q->skip($total - 30)->take(30)->get();

        // Lesestand vor dem Markieren: davor ist alles Neue seit dem letzten Besuch (Trenner "Neu")
        $neuAb = $gespraech->participant($user)?->last_read_at;
        $this->chat->markRead($gespraech, $user);

        return view('gespraech.show', [
            'conv' => $gespraech,
            'messages' => $messages,
            'neuAb' => $neuAb,
            'versteckt' => $alle ? 0 : max(0, $total - 30),
            'gelesenBis' => $this->chat->readUntilByOthers($gespraech, $user),
            'gegenueber' => $this->gegenueber($gespraech, $user),
            'darfSprache' => true,
            // 1:1-Seite wie im alten Bereich: Sitzungen im Paket und der Weg zum Termin
            'kontingent' => $gespraech->isDirect() && ! Ansicht::teamSicht($user) ? app(Lage::class)->kontingent($user) : null,
            'buchen' => $gespraech->isDirect() && ! Ansicht::teamSicht($user) && app(GoogleCalendar::class)->aktiv(),
            'naechster' => $gespraech->isDirect() && ! Ansicht::teamSicht($user) ? Event::where('user_id', $user->id)->where('is_published', true)->where('starts_at', '>=', now()->subHour())->orderBy('starts_at')->first() : null,
        ]);
    }

    public function senden(NachrichtRequest $request, Conversation $gespraech): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('view', $gespraech);
        $data = $request->validated();

        $ref = $this->ref($data, $user);
        if ($request->leer() || (blank($data['body'] ?? null) && ! $request->hasFile('file') && ! $request->hasFile('audio') && ! $ref)) {
            return $request->expectsJson() ? response()->json(['fehler' => 'Schreib etwas.'], 422) : back()->with('fehler', 'Schreib etwas.');
        }

        $msg = $this->chat->send($gespraech, $user, [
            'body' => $data['body'] ?? null,
            'file' => $request->file('file'),
            'audio' => $request->file('audio'),
            'sek' => $data['sek'] ?? null,
            'transkript' => $data['transkript'] ?? null,
            'ref' => $ref,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $msg->id, 'html' => $this->render($gespraech, $msg->load(['user:id,name', 'reactions', 'ref']), $user)]);
        }

        return redirect()->route('gespraech.show', $gespraech)->withFragment('nachricht-'.$msg->id);
    }

    /** Angehaengtes Element: refs[] ("art:nummer") aus der Auswahl, oder ref_type/ref_id von der Schnittstelle; nur, was die Person anhaengen darf. */
    protected function ref(array $data, User $user): ?array
    {
        $anhaenge = app(Anhaenge::class);
        $ref = ! empty($data['refs'][0]) ? (string) $data['refs'][0] : (! empty($data['ref_id']) ? $data['ref_type'].':'.$data['ref_id'] : null);
        $ziel = $ref ? $anhaenge->finden($ref, $user) : null;

        return $ziel ? ['type' => $ziel->getMorphClass(), 'id' => $ziel->getKey()] : null;
    }

    /** Polling: neue Nachrichten seit id, plus Lesestand. */
    public function neu(Request $request, Conversation $gespraech): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('view', $gespraech);
        $seit = (int) $request->query('seit', 0);

        $neue = $gespraech->messages()->where('id', '>', $seit)->with(['user:id,name,avatar_path,updated_at', 'reactions', 'ref'])->get();
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
        Gate::authorize('view', $gespraech);
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
        abort_unless($conv, 404);
        Gate::authorize('view', $conv);
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
        abort_unless($conv, 404);
        Gate::authorize('view', $conv);
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

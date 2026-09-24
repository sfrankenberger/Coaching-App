<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Reflection;
use App\Programs\ProgramAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Wochenreflexion: drei Fragen, privat bis geteilt. Eine angefangene, noch
 * nicht geteilte Reflexion wird weitergeschrieben statt neu angelegt.
 */
class ReflexionController extends Controller
{
    public const FRAGEN = [
        'went_well' => ['✨', 'Was hat geklappt? Was war cool?', 'Auch die kleinen Dinge zählen.'],
        'challenges' => ['🌱', 'Wo gab es Herausforderungen?', 'Ohne Bewertung, nur benennen.'],
        'focus' => ['🎯', 'Fokus: Wo will ich hin? Meine nächsten Schritte.', 'Ein bis drei konkrete Schritte für die kommende Woche.'],
    ];

    public function __construct(protected ProgramAccess $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $entwurf = $request->query('refl')
            ? Reflection::where('user_id', $user->id)->where('visibility', 'private')->find((int) $request->query('refl'))
            : Reflection::where('user_id', $user->id)->where('visibility', 'private')->where('created_at', '>=', now()->subDays(6))->latest()->first();

        return view('reflexion.index', [
            'fragen' => self::FRAGEN,
            'entwurf' => $entwurf,
            'meine' => Reflection::where('user_id', $user->id)->with('program:id,title')->latest()->limit(30)->get(),
            'kurse' => $this->access->programsFor($user)->pluck('title', 'id'),
            'woche' => 'Woche '.now()->format('W').' ('.now()->translatedFormat('j. F Y').')',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'refl_id' => ['nullable', 'integer'],
            'went_well' => ['nullable', 'string', 'max:10000'],
            'challenges' => ['nullable', 'string', 'max:10000'],
            'focus' => ['nullable', 'string', 'max:10000'],
            'program_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:private,coach,program'],
        ]);

        $reflection = ! empty($data['refl_id'])
            ? Reflection::where('user_id', $user->id)->where('visibility', 'private')->find($data['refl_id'])
            : null;
        $reflection ??= new Reflection(['user_id' => $user->id, 'week_label' => 'Woche '.now()->format('W').' ('.now()->translatedFormat('j. F Y').')']);

        if (blank($data['went_well'] ?? null) && blank($data['challenges'] ?? null) && blank($data['focus'] ?? null)) {
            return back()->with('fehler', 'Schreib zuerst etwas in eines der drei Felder.');
        }

        $programId = null;
        if (! empty($data['program_id']) && ($p = Program::find($data['program_id'])) && $this->access->canView($user, $p)) {
            $programId = $p->id;
        }
        $visibility = $data['visibility'] ?? 'private';
        if ($visibility === 'program' && ! $programId) {
            $visibility = 'coach';
        }

        $reflection->fill([
            'went_well' => $data['went_well'] ?? null,
            'challenges' => $data['challenges'] ?? null,
            'focus' => $data['focus'] ?? null,
            'program_id' => $programId,
            'visibility' => $visibility,
            'shared_at' => $visibility !== 'private' ? ($reflection->shared_at ?? now()) : null,
        ])->save();

        return redirect()->route('reflexion.index')->with('meldung', $visibility === 'private' ? 'Reflexion gespeichert, nur für dich.' : 'Reflexion gespeichert und geteilt.');
    }

    /** Nachtrag an eine geteilte Reflexion. */
    public function nachtrag(Request $request, Reflection $reflexion): RedirectResponse
    {
        abort_unless($reflexion->user_id === $request->user()->id, 403);
        $data = $request->validate(['addendum' => ['required', 'string', 'max:10000']]);
        $reflexion->forceFill(['addendum' => trim(($reflexion->addendum ? $reflexion->addendum."\n\n" : '').$data['addendum'])])->save();

        return back()->with('meldung', 'Nachtrag gespeichert.');
    }

    public function teilen(Request $request, Reflection $reflexion): RedirectResponse
    {
        abort_unless($reflexion->user_id === $request->user()->id, 403);
        $visibility = $request->input('visibility') === 'private' ? 'private' : ($reflexion->program_id && $request->input('visibility') === 'program' ? 'program' : 'coach');
        $reflexion->forceFill(['visibility' => $visibility, 'shared_at' => $visibility === 'private' ? null : ($reflexion->shared_at ?? now())])->save();

        return back()->with('meldung', $visibility === 'private' ? 'Nur noch für dich.' : 'Geteilt.');
    }

    public function destroy(Request $request, Reflection $reflexion): RedirectResponse
    {
        abort_unless($reflexion->user_id === $request->user()->id, 403);
        $reflexion->delete();

        return redirect()->route('reflexion.index')->with('meldung', 'Reflexion gelöscht.');
    }
}

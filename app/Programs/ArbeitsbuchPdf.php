<?php

namespace App\Programs;

use App\Models\Answer;
use App\Models\Exercise;
use App\Models\Note;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\Branding;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Das ausgefuellte Arbeitsbuch einer Person als PDF: alle Einheiten der Reihe nach,
 * jede Uebung mit Frage und Antwort, dazu die eigene Notiz zur Einheit.
 */
class ArbeitsbuchPdf
{
    public function __construct(protected Branding $branding) {}

    public function erzeugen(User $user, Program $program): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', resource_path('views'));

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('kurse.pdf', $this->daten($user, $program))->render());
        $pdf->render();

        return (string) $pdf->output();
    }

    public function dateiname(User $user, Program $program): string
    {
        return Str::slug($program->title).'-'.Str::slug($user->vorname()).'-'.now()->format('Y-m-d').'.pdf';
    }

    /** Einheiten mit Uebungen, Antworten und Notiz, in Kursreihenfolge. */
    public function daten(User $user, Program $program): array
    {
        $program->loadMissing(['steps', 'units.exercises', 'units.step']);
        $units = $program->orderedUnits();
        $exerciseIds = $units->flatMap(fn (Unit $u) => $u->exercises->pluck('id'));
        $answers = Answer::where('user_id', $user->id)->whereIn('exercise_id', $exerciseIds)->get()->keyBy('exercise_id');
        $notizen = Note::where('user_id', $user->id)->where('notable_type', 'unit')->whereIn('notable_id', $units->pluck('id'))->get()->keyBy('notable_id');

        $einheiten = $units->map(fn (Unit $u) => [
            'unit' => $u,
            'schritt' => $u->step?->title,
            'teile' => $u->exercises->sortBy('position')->values()->map(fn (Exercise $e) => $this->teil($e, $answers, $u))->filter()->values(),
            'notiz' => $notizen->get($u->id)?->body,
        ])->filter(fn ($e) => $e['teile']->isNotEmpty() || filled($e['notiz']))->values();

        return [
            'program' => $program,
            'user' => $user,
            'einheiten' => $einheiten,
            'coach' => $this->branding->coachName(),
            'app' => $this->branding->appName(),
            'farbe' => (string) $this->branding->get('primary', '#4A6C8C'),
            'datum' => now()->translatedFormat('j. F Y'),
        ];
    }

    /** Ein Uebungsteil als druckbare Zeile, null wenn er im PDF nichts zu suchen hat. */
    protected function teil(Exercise $e, Collection $answers, Unit $unit): ?array
    {
        $v = $answers->get($e->id)?->value['v'] ?? null;
        $frage = $e->prompt ?: $e->title;
        $t = ['art' => $e->type, 'frage' => $frage];

        return match ($e->type) {
            'heading' => $t + ['text' => $e->title ?: $e->prompt],
            'hint' => $t + ['text' => $e->prompt ?: $e->title],
            'text', 'note', 'letter' => $t + ['text' => is_array($v) ? implode(', ', $v) : (string) $v],
            'scale' => $t + ['wert' => (int) $v],
            'values', 'choice' => $t + ['optionen' => (array) ($e->options['values'] ?? []), 'gewaehlt' => (array) $v],
            'checkbox' => $t + ['haken' => (bool) $v],
            'list' => $t + ['zeilen' => array_values(array_filter((array) $v, fn ($z) => is_string($z) && trim($z) !== ''))],
            'pairs' => $t + ['links' => $e->options['links'] ?? 'Der Gedanke', 'rechts' => $e->options['rechts'] ?? 'Umgedreht', 'paare' => array_values(array_filter((array) $v, fn ($z) => is_array($z) && trim(implode('', $z)) !== ''))],
            'mirror' => $t + ['text' => Exercise::alsText($answers->get((int) ($e->options['exercise_id'] ?? 0))?->value['v'] ?? null)],
            'audio' => $t + ['text' => $answers->get($e->id)?->isFilled() ? 'Eine Aufnahme liegt in der App.' : ''],
            'practice' => $t + ['text' => is_string($v) && $v !== '' ? 'Gestartet am '.Carbon::parse($v)->translatedFormat('j. F Y') : ''],
            'wheel' => $t + ['skalen' => $unit->exercises->where('type', 'scale')->sortBy('position')->values()->map(fn (Exercise $s) => ['name' => $s->prompt ?: $s->title, 'wert' => (int) ($answers->get($s->id)?->value['v'] ?? 0)])->all()],
            default => null,
        };
    }
}

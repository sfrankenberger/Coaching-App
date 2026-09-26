<?php

namespace App\Http\Requests;

use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Unit;
use App\Programs\ProgramAccess;
use Illuminate\Foundation\Http\FormRequest;

/** Eigene Aufgabe anlegen oder aendern. */
class AufgabeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'is_daily' => ['nullable', 'boolean'],
            'program_id' => ['nullable', 'integer'],
            'step_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:private,coach,program'],
            'is_pinned' => ['nullable', 'boolean'],
        ];
    }

    /** Geprueft und bereinigt: Programm nur mit Zugang, Woche und Einheit nur aus dem Programm. */
    public function daten(): array
    {
        $data = $this->validated();
        $data['is_daily'] = (bool) ($data['is_daily'] ?? false);
        $data['is_pinned'] = (bool) ($data['is_pinned'] ?? false);
        $data['visibility'] ??= 'private';
        if (! empty($data['program_id'])) {
            $program = Program::find($data['program_id']);
            $data['program_id'] = $program && app(ProgramAccess::class)->canView($this->user(), $program) ? $program->id : null;
        }
        if (! empty($data['step_id'])) {
            $data['step_id'] = ProgramStep::where('id', $data['step_id'])->where('program_id', $data['program_id'] ?? 0)->value('id');
        }
        if (! empty($data['unit_id'])) {
            $data['unit_id'] = Unit::where('id', $data['unit_id'])->where('program_id', $data['program_id'] ?? 0)->value('id');
        }
        if ($data['visibility'] === 'program' && empty($data['program_id'])) {
            $data['visibility'] = 'coach';
        }

        return $data;
    }
}

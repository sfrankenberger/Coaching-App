<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Sitzung buchen: gewaehlte Startzeit und Antworten auf die Vorbereitungsfragen. */
class BuchungRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'start' => ['required', 'integer'],
            'antworten' => ['nullable', 'array', 'max:10'],
            'antworten.*' => ['nullable', 'string', 'max:3000'],
        ];
    }
}

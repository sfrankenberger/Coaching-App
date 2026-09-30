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
            'refs' => ['nullable', 'array', 'max:12'],
            'refs.*' => ['string', 'max:40'],
            'ref' => ['nullable', 'string', 'max:120'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Frage an die Coachin im Kursraum. */
class FrageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:10000'],
            'visibility' => ['nullable', 'in:program,coach'],
        ];
    }
}

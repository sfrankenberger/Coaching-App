<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Nachricht im Gespraech: Text, Datei, Sprachnachricht, angehaengtes Element. */
class NachrichtRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:10000'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,mp3,m4a,docx,txt'],
            'audio' => ['nullable', 'file', 'max:30720'],
            'sek' => ['nullable', 'integer'],
            'transkript' => ['nullable', 'string', 'max:10000'],
            'ref_type' => ['nullable', 'in:task,note,reflection,event,resource,unit'],
            'ref_id' => ['nullable', 'integer'],
        ];
    }

    public function leer(): bool
    {
        return blank($this->input('body')) && ! $this->hasFile('file') && ! $this->hasFile('audio') && empty($this->input('ref_id'));
    }
}

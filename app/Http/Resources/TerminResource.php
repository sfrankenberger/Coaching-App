<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'titel' => $this->title, 'art' => $this->type, 'beginn' => $this->starts_at->toIso8601String(), 'ende' => $this->ends_at?->toIso8601String(),
            'ganztags' => (bool) $this->all_day, 'zoom' => $this->zoom_url, 'aufzeichnung' => $this->hasRecording(), 'kurs' => $this->program?->title, 'url' => route('termine.show', $this->resource),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KursResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'slug' => $this->slug, 'titel' => $this->title, 'untertitel' => $this->subtitle, 'art' => $this->type,
            'farbe' => $this->color, 'bild' => $this->cover_url, 'stand' => $this->stand ?? null, 'url' => route('kurse.show', $this->resource),
        ];
    }
}

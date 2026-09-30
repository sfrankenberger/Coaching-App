<?php

namespace App\Filament\Coach\Resources\Kontakte\Pages;

use App\Filament\Coach\Resources\Kontakte\KontaktResource;
use App\Models\Kontakt;
use App\Newsletter\Kontakte;
use Filament\Resources\Pages\CreateRecord;

class CreateKontakt extends CreateRecord
{
    protected static string $resource = KontaktResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tags'] = array_values(array_unique(array_map([Kontakt::class, 'tagSauber'], (array) ($data['tags'] ?? []))));
        $data['einwilligung'] = ['herkunft' => 'von Hand', 'zeit' => now()->toIso8601String(), 'durch' => auth()->user()?->name];
        if (($data['status'] ?? null) === 'bestaetigt') {
            $data['bestaetigt_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $k = $this->record;
        if ($k->status === 'angemeldet') {
            app(Kontakte::class)->bestaetigungSchicken($k);
        } elseif ($k->istBestaetigt()) {
            $tags = $k->tags ?? [];
            $k->forceFill(['tags' => []])->save();
            app(Kontakte::class)->taggen($k, $tags);   // loest Serien aus
        }
    }
}

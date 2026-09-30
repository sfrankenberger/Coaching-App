<?php

namespace App\Filament\Coach\Resources\Kontakte\Pages;

use App\Filament\Coach\Resources\Kontakte\KontaktResource;
use App\Models\Kontakt;
use App\Newsletter\Kontakte;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKontakt extends EditRecord
{
    protected static string $resource = KontaktResource::class;

    protected array $tagsVorher = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->tagsVorher = $this->record->tags ?? [];
        $neu = array_values(array_unique(array_map([Kontakt::class, 'tagSauber'], (array) ($data['tags'] ?? []))));
        $data['tags'] = array_values(array_intersect($this->tagsVorher, $neu));   // neue Tags kommen ueber taggen() (Serien)
        $this->neueTags = array_values(array_diff($neu, $this->tagsVorher));
        if (($data['status'] ?? null) === 'bestaetigt' && ! $this->record->bestaetigt_at) {
            $data['bestaetigt_at'] = now();
        }

        return $data;
    }

    protected array $neueTags = [];

    protected function afterSave(): void
    {
        if ($this->neueTags) {
            app(Kontakte::class)->taggen($this->record->fresh(), $this->neueTags);
        }
    }
}

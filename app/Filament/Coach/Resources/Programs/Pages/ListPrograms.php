<?php

namespace App\Filament\Coach\Resources\Programs\Pages;

use App\Filament\Coach\Resources\Programs\ProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramResource::class;

    /** Kurse, Club und Hybrid sind das, was Lea baut. 1:1-Begleitungen entstehen je Person und stehen getrennt. */
    public function getTabs(): array
    {
        return [
            'kurse' => Tab::make('Kurse und Programme')->modifyQueryUsing(fn (Builder $query) => $query->where('type', '!=', 'one_on_one')),
            'einzeln' => Tab::make('1:1 Begleitungen (je Person)')->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'one_on_one')),
            'alle' => Tab::make('Alle'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Programm anlegen'),
        ];
    }
}

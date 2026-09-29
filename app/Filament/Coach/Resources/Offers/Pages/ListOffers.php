<?php

namespace App\Filament\Coach\Resources\Offers\Pages;

use App\Filament\Coach\Resources\Offers\OfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;

    /** Was verkauft wird, einmal je Angebot. Die Zugaenge je Person aus dem Import (ein Angebot je 1:1-Begleitung) stehen getrennt. */
    public function getTabs(): array
    {
        $jePerson = fn (Builder $query) => $query->whereHas('programs', fn (Builder $p) => $p->where('type', 'one_on_one'));

        return [
            'aktiv' => Tab::make('Angebote')->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)->whereDoesntHave('programs', fn (Builder $p) => $p->where('type', 'one_on_one'))),
            'einzeln' => Tab::make('1:1 je Person')->modifyQueryUsing($jePerson),
            'inaktiv' => Tab::make('Inaktiv')->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
            'alle' => Tab::make('Alle'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Angebot anlegen')];
    }
}

<?php

namespace App\Filament\Plattform\Resources\Tenants\Pages;

use App\Filament\Plattform\Resources\Tenants\TenantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Mandant anlegen')];
    }
}

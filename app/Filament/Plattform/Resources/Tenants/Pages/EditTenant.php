<?php

namespace App\Filament\Plattform\Resources\Tenants\Pages;

use App\Filament\Plattform\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;
}

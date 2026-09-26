<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CoachPanelProvider;
use App\Providers\Filament\PlattformPanelProvider;

return [
    AppServiceProvider::class,
    CoachPanelProvider::class,
    PlattformPanelProvider::class,
];

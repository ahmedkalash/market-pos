<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CompanyPanelProvider;
use App\Providers\StorageCleanupServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    CompanyPanelProvider::class,
    TelescopeServiceProvider::class,
    StorageCleanupServiceProvider::class,
];

<?php

declare(strict_types=1);

use App\Providers\ActivityLogServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    ActivityLogServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
    HorizonServiceProvider::class,
];

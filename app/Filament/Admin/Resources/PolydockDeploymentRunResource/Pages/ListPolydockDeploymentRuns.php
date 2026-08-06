<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockDeploymentRunResource\Pages;

use App\Filament\Admin\Resources\PolydockDeploymentRunResource;
use Filament\Resources\Pages\ListRecords;

class ListPolydockDeploymentRuns extends ListRecords
{
    protected static string $resource = PolydockDeploymentRunResource::class;
}

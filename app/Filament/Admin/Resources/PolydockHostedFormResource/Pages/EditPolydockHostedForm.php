<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockHostedFormResource\Pages;

use App\Filament\Admin\Resources\PolydockHostedFormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolydockHostedForm extends EditRecord
{
    protected static string $resource = PolydockHostedFormResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

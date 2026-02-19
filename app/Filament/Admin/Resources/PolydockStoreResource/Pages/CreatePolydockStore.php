<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePolydockStore extends CreateRecord
{
    protected static string $resource = PolydockStoreResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $key = $data['lagoon_deploy_private_key'] ?? null;
        unset($data['lagoon_deploy_private_key']);

        $record = static::getModel()::create($data);

        if ($key) {
            $record->setPolydockVariableValue('lagoon_deploy_private_key', $key, true);
        }

        return $record;
    }
}

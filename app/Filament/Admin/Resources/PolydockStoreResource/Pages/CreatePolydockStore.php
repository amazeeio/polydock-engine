<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePolydockStore extends CreateRecord
{
    protected static string $resource = PolydockStoreResource::class;

    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        $orgKeyId = $data['lagoon_deploy_organization_key_id'] ?? null;
        unset($data['lagoon_deploy_organization_key_id']);

        $record = static::getModel()::create($data);

        $record->setPolydockVariableValue('lagoon_deploy_organization_key_id', is_numeric($orgKeyId) ? (string) $orgKeyId : null);

        return $record;
    }
}

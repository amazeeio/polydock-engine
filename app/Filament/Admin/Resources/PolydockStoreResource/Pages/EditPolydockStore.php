<?php

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPolydockStore extends EditRecord
{
    protected static string $resource = PolydockStoreResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(
                    fn () => $this->record
                        ->apps()
                        ->whereHas('instances')
                        ->exists(),
                ),
        ];
    }

    #[\Override]
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    #[\Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $orgKeyId = $data['lagoon_deploy_organization_key_id'] ?? null;
        unset($data['lagoon_deploy_organization_key_id']);

        $record->update($data);

        $record->setPolydockVariableValue('lagoon_deploy_organization_key_id', is_numeric($orgKeyId) ? (string) $orgKeyId : null);

        return $record;
    }
}

<?php

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPolydockStore extends EditRecord
{
    protected static string $resource = PolydockStoreResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(
                    fn () => $this->record
                        ->apps()
                        ->whereHas('instances')
                        ->exists(),
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $key = $data['lagoon_deploy_private_key'] ?? null;
        unset($data['lagoon_deploy_private_key']);

        $record->update($data);

        if ($key) {
            $record->setPolydockVariableValue('lagoon_deploy_private_key', $key, true);
        }

        return $record;
    }
}

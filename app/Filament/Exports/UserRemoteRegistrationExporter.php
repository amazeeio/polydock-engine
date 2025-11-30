<?php

namespace App\Filament\Exports;

use App\Models\UserRemoteRegistration;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class UserRemoteRegistrationExporter extends Exporter
{
    protected static ?string $model = UserRemoteRegistration::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('uuid')
                ->label('UUID'),
            ExportColumn::make('type'),
            ExportColumn::make('email'),
            ExportColumn::make('user.id'),
            ExportColumn::make('userGroup.name'),
            // ExportColumn::make('polydock_app_instance_id'),
            // ExportColumn::make('polydock_store_app_id'),
            ExportColumn::make('polydock_store_app.name')
                ->label('Store App'),
            // ExportColumn::make('request_data'),
            // ExportColumn::make('result_data'),
            ExportColumn::make('status'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your user remote registration export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}

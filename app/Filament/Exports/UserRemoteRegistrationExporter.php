<?php

namespace App\Filament\Exports;

use App\Models\PolydockAppInstance;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class UserRemoteRegistrationExporter extends Exporter
{
    protected static ?string $model = PolydockAppInstance::class;

    /**
     * Disable queueing for this exporter so exports run synchronously.
     */
    public static function shouldQueue(): bool
    {
        return false;
    }

    public static function getValueFromData($data, $key)
    {
        if (! empty($data[$key])) {
            return $data[$key];
        }

        return '';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('storeApp.name')
                ->label('Store App Name'),
            ExportColumn::make('status')
                ->state(fn (PolydockAppInstance $record) => $record->getStatus()->toString())
                ->label('Registration Status'),
            ExportColumn::make('email')
                ->state(fn (PolydockAppInstance $record) => UserRemoteRegistrationExporter::getValueFromData(
                    $record->data,
                    'user-email',
                ))
                ->label('Email Address'),
            ExportColumn::make('first-name')
                ->state(fn (PolydockAppInstance $record) => UserRemoteRegistrationExporter::getValueFromData(
                    $record->data,
                    'user-first-name',
                ))
                ->label('First Name'),
            ExportColumn::make('last-name')
                ->state(fn (PolydockAppInstance $record) => UserRemoteRegistrationExporter::getValueFromData(
                    $record->data,
                    'user-last-name',
                ))
                ->label('Last Name'),
            ExportColumn::make('company-name')
                ->state(fn (PolydockAppInstance $record) => UserRemoteRegistrationExporter::getValueFromData(
                    $record->data,
                    'company-name',
                ))
                ->label('Company name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body =
            'Your user remote registration export has completed and '
            .number_format($export->successful_rows)
            .' '
            .str('row')->plural($export->successful_rows)
            .' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

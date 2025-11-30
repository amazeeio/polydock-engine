<?php

namespace App\ReportExporters;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;


class ReportExporterGeneric implements ReportExporterInterface {
    public static function getColumns(): array {
        return [
            ExportColumn::make('polydock_store_app')
        ];
    }
}
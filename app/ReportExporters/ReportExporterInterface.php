<?php

namespace App\ReportExporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

interface ReportExporterInterface {
    public static function getColumns(): array;
}
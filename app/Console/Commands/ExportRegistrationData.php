<?php

namespace App\Console\Commands;

use App\Models\UserRemoteRegistration;
use App\Support\SensitiveDataRedactor;
use Illuminate\Support\Facades\Storage;

class ExportRegistrationData extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:export-registration-data
                          {--format=csv : Export format (csv, json)}
                          {--file= : Custom filename (without extension)}
                          {--stdout : Output to STDOUT instead of file}
                          {--status= : Filter by status}
                          {--type= : Filter by registration type}
                          {--store= : Filter by store name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export user registration data matching the Filament UserRemoteRegistrations resource';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = UserRemoteRegistration::with([
            'user',
            'userGroup',
            'storeApp.store',
            'appInstance',
        ]);

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        if ($store = $this->option('store')) {
            $query->whereHas('storeApp.store', function ($q) use ($store) {
                $q->where('name', 'like', "%{$store}%");
            });
        }

        $registrations = $query->get();

        if ($registrations->isEmpty()) {
            $this->warn('No registration data found matching the criteria.');

            return self::FAILURE;
        }

        $exportData = $registrations->map(function (UserRemoteRegistration $registration) {
            return [
                'id' => $registration->id,
                'type' => $registration->type?->value ?? '',
                'email' => $registration->email,
                'user_name' => $registration->user?->name ?? '',
                'user_group_name' => $registration->userGroup?->name ?? '',
                'store_name' => $registration->storeApp?->store?->name ?? '',
                'store_app_name' => $registration->storeApp?->name ?? '',
                'status' => $registration->status->value,
                'created_at' => $registration->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $registration->updated_at?->format('Y-m-d H:i:s'),
                'app_instance_name' => $registration->appInstance?->name ?? '',
                'app_instance_url' => $registration->appInstance?->app_url ?? '',
                'request_data' => json_encode(SensitiveDataRedactor::redact($registration->request_data ?? [])),
            ];
        });

        $format = $this->option('format') === 'json' ? 'json' : 'csv';

        $content = $format === 'json'
            ? $exportData->toJson(JSON_PRETTY_PRINT)
            : $this->generateCsv($exportData->all());

        if ($this->option('stdout')) {
            $this->output->write($content);

            return self::SUCCESS;
        }

        $filename = ($this->option('file') ?? 'user_registrations_'.now()->format('Y-m-d_H-i-s')).'.'.$format;
        Storage::put("exports/{$filename}", $content);

        $this->info('Registration data exported successfully.');
        $this->line('   Format: '.strtoupper($format));
        $this->line("   Records: {$registrations->count()}");
        $this->line('   File: '.Storage::path("exports/{$filename}"));

        return self::SUCCESS;
    }

    /**
     * Generate CSV content from the export rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function generateCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}

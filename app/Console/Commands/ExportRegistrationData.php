<?php

namespace App\Console\Commands;

use App\Models\UserRemoteRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportRegistrationData extends Command
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
    protected $description = 'Export user registration data matching Filament UserRemoteRegistrationsResource';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Exporting user registration data...');

        // Build query with optional filters
        $query = UserRemoteRegistration::with([
            'user',
            'userGroup', 
            'storeApp.store',
            'storeApp',
            'appInstance'
        ]);

        // Apply filters
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
            return 1;
        }

        $this->info("Found {$registrations->count()} registration records.");

        // Prepare data in the same format as Filament table
        $exportData = $registrations->map(function ($registration) {
            return [
                'id' => $registration->id,
                // 'uuid' => $registration->uuid,
                'type' => $registration->type?->value ?? '',
                'email' => $registration->email,
                'user_name' => $registration->user?->name ?? '',
                'user_group_name' => $registration->userGroup?->name ?? '',
                'store_name' => $registration->storeApp?->store?->name ?? '',
                'store_app_name' => $registration->storeApp?->name ?? '',
                'status' => $registration->status->value,
                'created_at' => $registration->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $registration->updated_at?->format('Y-m-d H:i:s'),
                // 'app_instance_id' => $registration->appInstance?->id ?? '',
                'app_instance_name' => $registration->appInstance?->name ?? '',
                'app_instance_url' => $registration->appInstance?->app_url ?? '',
                'request_data' => json_encode($registration->request_data),
                // 'result_data' => json_encode($registration->result_data),
            ];
        });

        // Generate filename
        $format = $this->option('format');
        $filename = $this->option('file') ?? 'user_registrations_' . now()->format('Y-m-d_H-i-s');
        $fullFilename = $filename . '.' . $format;

        // Export based on format
        if ($format === 'json') {
            $content = $exportData->toJson(JSON_PRETTY_PRINT);
        } else {
            // Default to CSV
            $content = $this->generateCsv($exportData);
        }

        // Output to STDOUT or save to file
        if ($this->option('stdout')) {
            $this->output->write($content);
        } else {
            // Save to storage
            Storage::put("exports/{$fullFilename}", $content);
            
            $filePath = storage_path("app/exports/{$fullFilename}");
            
            $this->info("✅ Registration data exported successfully!");
            $this->line("   Format: " . strtoupper($format));
            $this->line("   Records: {$registrations->count()}");
            $this->line("   File: {$filePath}");
        }

        return 0;
    }

    /**
     * Generate CSV content from the export data
     */
    private function generateCsv($data): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        $headers = array_keys($data->first());
        $csv = implode(',', $headers) . "\n";

        foreach ($data as $row) {
            $csv .= implode(',', array_map(function ($value) {
                // Escape quotes and wrap in quotes if contains comma, quote, or newline
                if (is_string($value) && (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false)) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }
                return $value;
            }, $row)) . "\n";
        }

        return $csv;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\LagoonClientService;
use Illuminate\Console\Command;

class BulkDeployStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:bulk-deploy:status {bulk_id : The bulk deployment ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check status of a bulk deployment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bulkId = $this->argument('bulk_id');

        $this->info("Fetching status for bulk deployment ID: {$bulkId}...");

        try {
            $client = app(LagoonClientService::class)->getAuthenticatedClient();
            $deployments = $client->getDeploymentsByBulkId($bulkId);

            if (isset($deployments['error'])) {
                $errors = is_array($deployments['error']) ? json_encode($deployments['error']) : $deployments['error'];
                $this->error("Failed to fetch bulk deployment status: {$errors}");

                return 1;
            }

            if (empty($deployments)) {
                $this->warn("No deployments found for bulk ID: {$bulkId}");

                return 0;
            }

            $total = count($deployments);
            $stats = [
                'new' => 0,
                'pending' => 0,
                'running' => 0,
                'complete' => 0,
                'failed' => 0,
                'cancelled' => 0,
            ];

            $rows = [];
            foreach ($deployments as $deploy) {
                $status = $deploy['status'] ?? 'unknown';
                $stats[$status] = ($stats[$status] ?? 0) + 1;

                $projectName = $deploy['environment']['project']['name'] ?? 'unknown';
                $envName = $deploy['environment']['name'] ?? 'unknown';

                $rows[] = [
                    $deploy['id'] ?? 'unknown',
                    $projectName,
                    $envName,
                    $status,
                    $deploy['started'] ?? '',
                    $deploy['completed'] ?? '',
                ];
            }

            $this->info("\nBulk Deployment Summary:");
            $this->info("Total: {$total}");
            foreach ($stats as $status => $count) {
                if ($count > 0) {
                    $color = $this->getStatusColor($status);
                    $this->line("<fg={$color}>{$status}: {$count}</>");
                }
            }

            $this->newLine();
            $this->table(
                ['ID', 'Project', 'Environment', 'Status', 'Started', 'Completed'],
                $rows
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Error checking bulk deployment status: {$e->getMessage()}");

            return 1;
        }
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'complete' => 'green',
            'failed' => 'red',
            'running' => 'blue',
            'pending' => 'yellow',
            'cancelled' => 'gray',
            default => 'white',
        };
    }
}

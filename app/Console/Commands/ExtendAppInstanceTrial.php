<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;

class ExtendAppInstanceTrial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:extend-trial
                          {identifier : The UUID or Email of the app instance(s)}
                          {date : The new end date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)}
                          {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extend the trial date for specific app instance(s) by UUID or Email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $identifier = $this->argument('identifier');
        $date = $this->argument('date');
        $force = $this->option('force');

        try {
            $newEndDate = Carbon::parse($date);
        } catch (\Exception $e) {
            $this->error("Invalid date format: {$date}");

            return 1;
        }

        // Ensure the date is in the future
        if ($newEndDate->isPast()) {
            $this->warn("Warning: The provided date {$newEndDate->toDateTimeString()} is in the past.");
        }

        $instances = collect();

        // Try to find by UUID first
        if (Str::isUuid($identifier)) {
            $instance = PolydockAppInstance::where('uuid', $identifier)->first();
            if ($instance) {
                $instances->push($instance);
            }
        } else {
            // Try to find by Email
            $instances = PolydockAppInstance::where('data->user-email', $identifier)->get();
        }

        $count = $instances->count();

        if ($count === 0) {
            $this->error("No app instances found for identifier: {$identifier}");

            return 1;
        }

        $this->info("Found {$count} instance(s).");

        if (! $force) {
            // Pre-calculate column widths
            $maxWidths = [
                'id' => strlen('ID'),
                'name' => strlen('Name'),
                'email' => strlen('Email'),
                'end' => strlen('Current End'),
            ];

            $instanceData = [];

            foreach ($instances as $instance) {
                $email = $instance->getKeyValue('user-email');
                $currentEnd = $instance->trial_ends_at ? $instance->trial_ends_at->toDateTimeString() : 'N/A';

                $data = [
                    'id' => (string) $instance->id,
                    'name' => $instance->name,
                    'email' => $email,
                    'end' => $currentEnd,
                ];

                $instanceData[$instance->id] = $data;

                foreach ($maxWidths as $key => $width) {
                    $maxWidths[$key] = max($width, strlen((string) $data[$key]));
                }
            }

            // Create formatted options
            $options = [];
            foreach ($instanceData as $id => $data) {
                $label = sprintf(
                    '%s  %s  %s  %s',
                    str_pad($data['id'], $maxWidths['id']),
                    str_pad((string) $data['name'], $maxWidths['name']),
                    str_pad((string) $data['email'], $maxWidths['email']),
                    str_pad((string) $data['end'], $maxWidths['end'])
                );
                $options[$id] = $label;
            }

            // Create a header for the prompt label
            $header = sprintf(
                '%s  %s  %s  %s',
                str_pad('ID', $maxWidths['id']),
                str_pad('Name', $maxWidths['name']),
                str_pad('Email', $maxWidths['email']),
                str_pad('Current End', $maxWidths['end'])
            );

            $selectedIds = multiselect(
                label: 'Select instances to extend trial for:',
                options: $options,
                default: array_keys($options),
                scroll: 15,
                hint: $header
            );

            if (empty($selectedIds)) {
                $this->info('No instances selected.');

                return 0;
            }

            // Filter instances
            $instances = $instances->whereIn('id', $selectedIds);

            if (! $this->confirm("Are you sure you want to update the trial date to {$newEndDate->toDateTimeString()} for ".$instances->count().' instances?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        } else {
            $this->info('Force mode enabled. Updating all found instances.');
        }

        foreach ($instances as $instance) {
            try {
                $this->info("Updating instance {$instance->name} (ID: {$instance->id})...");
                $instance->calculateAndSetTrialDatesFromEndDate($newEndDate, true);
                $instance->refresh();
                $this->info('  -> New Trial Ends At: '.$instance->trial_ends_at->toDateTimeString());
            } catch (\Exception $e) {
                $this->error("  -> Failed to update: {$e->getMessage()}");
            }
        }

        return 0;
    }
}

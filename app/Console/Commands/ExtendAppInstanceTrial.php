<?php

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ExtendAppInstanceTrial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:extend-trial
                          {id : The ID of the app instance}
                          {date : The new end date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)}
                          {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extend the trial date for a specific app instance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $date = $this->argument('date');
        $force = $this->option('force');

        $instance = PolydockAppInstance::find($id);

        if (!$instance) {
            $this->error("App instance not found with ID: {$id}");
            return 1;
        }

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

        $this->info("App Instance: {$instance->name} (ID: {$instance->id})");
        $this->info("Current Trial End: " . ($instance->trial_ends_at ? $instance->trial_ends_at->toDateTimeString() : 'N/A'));
        $this->info("New Trial End (Approx): {$newEndDate->toDateTimeString()}");
        $this->info("Note: The exact time will be calculated relative to now() based on day difference.");
        
        // Confirm unless force flag is used
        if (!$force) {
            if (!$this->confirm('Do you want to proceed with updating the trial date?', true)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $instance->calculateAndSetTrialDatesFromEndDate($newEndDate, true);
            $this->info("Successfully updated trial dates for app instance {$id}.");
            // Reload to get the actual set date
            $instance->refresh();
            $this->info("New Trial Ends At: " . $instance->trial_ends_at->toDateTimeString());
        } catch (\Exception $e) {
            $this->error("Failed to update trial dates: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}

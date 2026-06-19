<?php

namespace App\Console\Commands;

use App\Services\EmailBlockerService;
use Illuminate\Console\Command;

class UpdateDisposableDomains extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:update-disposable-domains';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and cache the latest list of disposable email domains from GitHub';

    /**
     * Execute the console command.
     */
    public function handle(EmailBlockerService $service): int
    {
        $this->info('Downloading latest disposable email domains list...');
        $count = $service->updateDisposableDomains();

        if ($count > 0) {
            $this->info("Successfully updated disposable email domains list! Cached {$count} domains.");

            return self::SUCCESS;
        }

        $this->error('Failed to update disposable email domains. Falling back to cached list.');

        return self::FAILURE;
    }
}

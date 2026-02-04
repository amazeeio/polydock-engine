<?php

namespace App\Console\Commands;

use App\Models\PolydockStore;
use App\Models\PolydockStoreWebhook;
use Illuminate\Console\Command;

class AttachWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:attach-webhook
                          {--store-id= : Store ID to attach webhook to}
                          {--url= : Webhook URL}
                          {--active= : Whether webhook is active (true/false)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach a webhook to a Polydock Store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Attaching webhook to Polydock Store...');

        // Get all stores for selection
        $stores = PolydockStore::all();

        if ($stores->isEmpty()) {
            $this->error('No stores found. Please create a store first.');

            return 1;
        }

        // Select store
        $storeId = $this->option('store-id');
        if (! $storeId) {
            $storeOptions = $stores->mapWithKeys(fn ($store) => [$store->id => "{$store->name} (ID: {$store->id})"])->toArray();

            $selectedStoreValue = $this->choice('Select a store to attach webhook to:', $storeOptions);
            $storeId = collect($storeOptions)->search($selectedStoreValue);
            if (empty($storeId)) {
                $this->error('Unable to find store ID');
                exit(1);
            }
        }

        // Validate store exists
        $store = PolydockStore::find($storeId);
        if (! $store) {
            $this->error("Store with ID {$storeId} not found.");

            return 1;
        }

        // Get webhook URL
        $webhookUrl = $this->option('url') ?? $this->ask('Webhook URL');
        if (empty($webhookUrl)) {
            $this->error('Webhook URL is required. Exiting...');

            return 1;
        }

        // Get active status
        $activeInput = $this->option('active') ?? $this->choice('Should the webhook be active?', ['true', 'false']);
        $active = filter_var($activeInput, FILTER_VALIDATE_BOOLEAN);

        // Create the webhook
        $webhook = PolydockStoreWebhook::create([
            'polydock_store_id' => $store->id,
            'url' => $webhookUrl,
            'active' => $active,
        ]);

        $this->info("✅ Webhook attached successfully to store '{$store->name}' with ID: {$webhook->id}");
        $this->line("   URL: {$webhook->url}");
        $this->line('   Active: '.($webhook->active ? 'Yes' : 'No'));

        return 0;
    }
}

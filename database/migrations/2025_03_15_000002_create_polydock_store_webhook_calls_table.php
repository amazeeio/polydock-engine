<?php

use App\Enums\PolydockStoreWebhookCallStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_store_webhook_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polydock_store_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->enum('status', PolydockStoreWebhookCallStatusEnum::getValues())
                ->default(PolydockStoreWebhookCallStatusEnum::PENDING->value);
            $table->integer('attempt')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->string('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('exception')->nullable();
            $table->timestamps();

            // Indexes for faster lookups
            $table->index('event', 'polydock_store_webhook_event_idx');
            $table->index('status', 'polydock_store_webhook_status_idx');
            $table->index('processed_at', 'polydock_store_webhook_processed_at_idx');
            $table->index(['polydock_store_webhook_id', 'event'], 'polydock_store_webhook_id_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_store_webhook_calls');
    }
};

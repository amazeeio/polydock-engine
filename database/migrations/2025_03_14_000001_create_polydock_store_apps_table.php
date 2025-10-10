<?php

use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_store_apps', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('polydock_store_id')->constrained()->cascadeOnDelete();
            $table->string('class');
            $table->string('name');
            $table->text('description');
            $table->string('author');
            $table->string('website')->nullable();
            $table->string('support_email');
            $table->string('lagoon_deploy_git');
            $table->string('lagoon_deploy_branch')->default('main');
            $table->enum('status', PolydockStoreAppStatusEnum::getValues())
                ->default(PolydockStoreAppStatusEnum::AVAILABLE->value);
            $table->boolean('available_for_trials')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_store_apps');
    }
};

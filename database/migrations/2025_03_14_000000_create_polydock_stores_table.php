<?php

use App\Enums\PolydockStoreStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', PolydockStoreStatusEnum::getValues())->default(PolydockStoreStatusEnum::PRIVATE->value);
            $table->boolean('listed_in_marketplace')->default(false);
            $table->string('lagoon_deploy_region_id');
            $table->string('lagoon_deploy_project_prefix');
            $table->string('lagoon_deploy_organization_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_stores');
    }
};

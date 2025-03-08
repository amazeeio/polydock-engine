<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;

return new class extends Migration
{
    public function up(): void
    {
        // Get the current enum values, excluding 'new' since we're adding it
        $currentEnumValues = implode("','", array_filter(
            PolydockAppInstanceStatus::getValues(),
            fn($value) => $value !== 'new'
        ));
        
        // Add NEW to the enum
        DB::statement("ALTER TABLE polydock_app_instances MODIFY COLUMN status ENUM('$currentEnumValues','new')");
    }

    public function down(): void
    {
        // Get the original enum values (excluding NEW)
        $originalEnumValues = array_filter(
            PolydockAppInstanceStatus::getValues(),
            fn($value) => $value !== 'new'
        );
        $enumString = implode("','", $originalEnumValues);
        
        // Remove NEW from the enum
        DB::statement("ALTER TABLE polydock_app_instances MODIFY COLUMN status ENUM('$enumString')");
    }
}; 
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * spatie/laravel-activitylog v5: tracked model changes move from
     * `properties` to the new `attribute_changes` column; the batch system
     * (batch_uuid) was removed.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->json('attribute_changes')->nullable()->after('causer_id');
            $table->dropColumn('batch_uuid');
        });

        DB::table('activity_log')->whereNotNull('properties')->eachById(function ($row) {
            $properties = json_decode((string) $row->properties, true) ?: [];
            $changes = array_intersect_key($properties, array_flip(['attributes', 'old']));
            $remaining = array_diff_key($properties, array_flip(['attributes', 'old']));

            if ($changes === []) {
                return;
            }

            DB::table('activity_log')->where('id', $row->id)->update([
                'attribute_changes' => json_encode($changes),
                'properties' => $remaining === [] ? null : json_encode($remaining),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('causer_id');
        });

        DB::table('activity_log')->whereNotNull('attribute_changes')->eachById(function ($row) {
            $properties = json_decode((string) $row->properties, true) ?: [];
            $changes = json_decode((string) $row->attribute_changes, true) ?: [];

            DB::table('activity_log')->where('id', $row->id)->update([
                'properties' => json_encode(array_merge($properties, $changes)),
            ]);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('attribute_changes');
        });
    }
};

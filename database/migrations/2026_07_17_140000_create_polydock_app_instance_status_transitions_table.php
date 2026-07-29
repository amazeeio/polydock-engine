<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'polydock_app_instance_status_transitions';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                // Explicit short name: the auto-generated one exceeds MySQL's
                // 64-char identifier limit.
                $table->foreignId('polydock_app_instance_id')
                    ->constrained(table: 'polydock_app_instances', indexName: 'papist_instance_fk')
                    ->cascadeOnDelete();
                // Null for the row recorded when an instance is created as NEW.
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->timestamp('created_at');

                $table->index(['polydock_app_instance_id', 'created_at'], 'papist_instance_created_idx');
            });

            return;
        }

        // Self-heal: MySQL DDL is not transactional, so a migrate run killed
        // between `create table` and the trailing FK alter leaves the table
        // behind with the migration unrecorded — every later deploy then dies
        // with "table already exists". Finish the missing pieces instead.
        if (! in_array('papist_instance_created_idx', Schema::getIndexListing(self::TABLE))) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['polydock_app_instance_id', 'created_at'], 'papist_instance_created_idx');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys(self::TABLE))
            ->contains(fn (array $fk) => $fk['columns'] === ['polydock_app_instance_id']);

        if (! $hasForeignKey) {
            // Rows orphaned while the cascade FK was missing would block the
            // constraint from applying.
            DB::table(self::TABLE)
                ->whereNotIn('polydock_app_instance_id', DB::table('polydock_app_instances')->select('id'))
                ->delete();

            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('polydock_app_instance_id', 'papist_instance_fk')
                    ->references('id')
                    ->on('polydock_app_instances')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};

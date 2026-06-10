<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the 'admin' value to the user_user_group.role ENUM column.
     *
     * The original migration created this column from UserGroupRoleEnum::getValues(),
     * which baked the value list ['owner', 'member', 'viewer'] into the database
     * schema. Adding ADMIN to the PHP enum does not update the column definition,
     * so existing deployments must explicitly extend the ENUM here.
     */
    public function up(): void
    {
        // SQLite stores enum columns as TEXT and enforces the constraint at the
        // application layer, so no schema change is needed there.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE user_user_group MODIFY COLUMN role ENUM('owner','admin','member','viewer') NOT NULL"
        );
    }

    /**
     * Reverse the migration.
     *
     * Demotes any 'admin' rows to 'member' before shrinking the ENUM, otherwise
     * MySQL would refuse to remove a value that is still referenced.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::table('user_user_group')->where('role', 'admin')->update(['role' => 'member']);

        DB::statement(
            "ALTER TABLE user_user_group MODIFY COLUMN role ENUM('owner','member','viewer') NOT NULL"
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->text('email_body_markdown')->nullable()->after('email_subject_line');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->dropColumn('email_body_markdown');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            // Trial duration
            $table->unsignedInteger('trial_duration_days')->nullable()->after('email_body_markdown');

            // Mid-trial email settings
            $table->boolean('send_midtrial_email')->default(false)->after('trial_duration_days');
            $table->string('midtrial_email_subject')->nullable()->after('send_midtrial_email');
            $table->text('midtrial_email_markdown')->nullable()->after('midtrial_email_subject');

            // One day left email settings
            $table->boolean('send_one_day_left_email')->default(false)->after('midtrial_email_markdown');
            $table->string('one_day_left_email_subject')->nullable()->after('send_one_day_left_email');
            $table->text('one_day_left_email_markdown')->nullable()->after('one_day_left_email_subject');

            // Trial complete email settings
            $table->boolean('send_trial_complete_email')->default(false)->after('one_day_left_email_markdown');
            $table->string('trial_complete_email_subject')->nullable()->after('send_trial_complete_email');
            $table->text('trial_complete_email_markdown')->nullable()->after('trial_complete_email_subject');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->dropColumn([
                'trial_duration_days',
                'send_midtrial_email',
                'midtrial_email_subject',
                'midtrial_email_markdown',
                'send_one_day_left_email',
                'one_day_left_email_subject',
                'one_day_left_email_markdown',
                'send_trial_complete_email',
                'trial_complete_email_subject',
                'trial_complete_email_markdown',
            ]);
        });
    }
};

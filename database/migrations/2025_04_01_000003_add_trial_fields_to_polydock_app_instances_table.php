<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            // Trial status fields
            $table->boolean('is_trial')->default(false)->after('status_message');
            $table->timestamp('trial_ends_at')->nullable()->after('is_trial');
            $table->boolean('trial_completed')->default(false)->after('trial_ends_at');

            // Mid-trial email tracking
            $table->timestamp('send_midtrial_email_at')->nullable()->after('trial_completed');
            $table->boolean('midtrial_email_sent')->default(false)->after('send_midtrial_email_at');

            // One day left email tracking
            $table->timestamp('send_one_day_left_email_at')->nullable()->after('midtrial_email_sent');
            $table->boolean('one_day_left_email_sent')->default(false)->after('send_one_day_left_email_at');

            // Trial complete email tracking
            $table->boolean('trial_complete_email_sent')->default(false)->after('one_day_left_email_sent');

            // Add indexes for common queries
            $table->index(['is_trial', 'trial_ends_at'], 'polydock_app_instances_trial_idx');
            $table->index(['is_trial', 'send_midtrial_email_at', 'midtrial_email_sent'], 'polydock_app_instances_midtrial_email_idx');
            $table->index(['is_trial', 'send_one_day_left_email_at', 'one_day_left_email_sent'], 'polydock_app_instances_one_day_left_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('polydock_app_instances', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('polydock_app_instances_trial_idx');
            $table->dropIndex('polydock_app_instances_midtrial_email_idx');
            $table->dropIndex('polydock_app_instances_one_day_left_email_idx');

            // Drop columns
            $table->dropColumn([
                'is_trial',
                'trial_ends_at',
                'trial_completed',
                'send_midtrial_email_at',
                'midtrial_email_sent',
                'send_one_day_left_email_at',
                'one_day_left_email_sent',
                'trial_complete_email_sent',
            ]);
        });
    }
};

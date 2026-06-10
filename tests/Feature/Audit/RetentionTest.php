<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_activitylog_clean_command_removes_entries_older_than_threshold(): void
    {
        // Create an old activity (400 days ago)
        activity()->log('Old action');
        $old = Activity::where('description', 'Old action')->first();
        $this->assertNotNull($old);
        $old->update(['created_at' => now()->subDays(400)]);

        // Create a recent activity (30 days ago)
        activity()->log('Recent action');
        $recent = Activity::where('description', 'Recent action')->first();
        $this->assertNotNull($recent);
        $recent->update(['created_at' => now()->subDays(30)]);

        // Run the clean command (default is 365 days)
        $this->artisan('activitylog:clean')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('activity_log', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $recent->id]);
    }

    public function test_activitylog_clean_keeps_entries_within_threshold(): void
    {
        // Create activity within the retention period
        activity()->log('Action 1');
        $activity1 = Activity::where('description', 'Action 1')->first();
        $this->assertNotNull($activity1);
        $activity1->update(['created_at' => now()->subDays(100)]);

        activity()->log('Action 2');
        $activity2 = Activity::where('description', 'Action 2')->first();
        $this->assertNotNull($activity2);
        $activity2->update(['created_at' => now()->subDays(364)]);

        $this->artisan('activitylog:clean')
            ->assertExitCode(0);

        $this->assertDatabaseHas('activity_log', ['id' => $activity1->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $activity2->id]);
    }
}

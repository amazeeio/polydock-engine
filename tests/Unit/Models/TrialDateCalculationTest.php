<?php

namespace Tests\Unit\Models;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TrialDateCalculationTest extends TestCase
{
    use RefreshDatabase;

    private PolydockStoreApp $storeApp;

    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'trial_duration_days' => 7,
            'send_midtrial_email' => true,
            'send_one_day_left_email' => true,
            'send_trial_complete_email' => true,
        ]);
        $this->group = UserGroup::factory()->create();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeInstance(): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->user_group_id = $this->group->id;
        $instance->name = 'trial-date-test';
        $instance->app_type = PolydockAiApp::class;
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save();

        return $instance;
    }

    public function test_whole_days_in_future_sets_trial_ends_at_exactly_that_many_days_out(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $instance = $this->makeInstance();
        $endDate = now()->copy()->addDays(5); // exactly 5 whole days

        $instance->calculateAndSetTrialDatesFromEndDate($endDate);

        $this->assertNotNull($instance->trial_ends_at);
        $this->assertSame(
            now()->copy()->addDays(5)->toDateTimeString(),
            $instance->trial_ends_at->toDateTimeString()
        );
    }

    public function test_partial_day_rounds_up_so_trial_is_not_short_changed(): void
    {
        // Regression: Carbon 3 diffInDays() returns a float (e.g. 5.5); truncation
        // toward zero would leave the trial ~1 day short of the requested end.
        Carbon::setTestNow('2026-07-01 12:00:00');

        $instance = $this->makeInstance();
        $endDate = now()->copy()->addDays(5)->addHours(13); // 5 days + 13h => rounds up to 6

        $instance->calculateAndSetTrialDatesFromEndDate($endDate);

        $this->assertNotNull($instance->trial_ends_at);
        // Rounds up to 6 whole days rather than truncating to 5.
        $this->assertSame(
            now()->copy()->addDays(6)->toDateTimeString(),
            $instance->trial_ends_at->toDateTimeString()
        );
        // And the trial never ends before the requested end date.
        $this->assertTrue(
            $instance->trial_ends_at->greaterThanOrEqualTo($endDate),
            'trial_ends_at should be on or after the requested end date'
        );
    }

    public function test_end_date_in_the_past_leaves_trial_ends_at_null(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $instance = $this->makeInstance();

        // Prime a valid trial first so the assertion actually exercises the
        // null-reset path rather than passing on a fresh instance's null.
        $instance->calculateAndSetTrialDates(7);
        $this->assertNotNull($instance->trial_ends_at);

        $endDate = now()->copy()->subDays(3); // in the past

        $instance->calculateAndSetTrialDatesFromEndDate($endDate);

        // Guard ($durationDays > 0) means no negative trial is set.
        $this->assertNull($instance->trial_ends_at);
    }
}

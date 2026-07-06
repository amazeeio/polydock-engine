<?php

namespace Tests\Feature\Jobs\Trial;

use App\Enums\UserGroupRoleEnum;
use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Jobs\ProcessPolydockAppInstanceJobs\Trial\ProcessMidtrialEmailJob;
use App\Mail\AppInstanceMidtrialMail;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessMidtrialEmailJobTest extends TestCase
{
    use RefreshDatabase;

    private PolydockAppInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent the NEW-status save below from dispatching the real
        // lifecycle jobs (which would reach out to Lagoon).
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'send_midtrial_email' => true,
        ]);

        $group = UserGroup::factory()->create();
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $group->users()->attach($owner->id, ['role' => UserGroupRoleEnum::OWNER->value]);

        $this->instance = new PolydockAppInstance;
        $this->instance->polydock_store_app_id = $storeApp->id;
        $this->instance->user_group_id = $group->id;
        $this->instance->name = 'midtrial-test';
        $this->instance->app_type = PolydockAiApp::class;
        $this->instance->status = PolydockAppInstanceStatus::NEW;
        $this->instance->is_trial = true;
        // Reminder is due (past) but the trial itself has not expired (future).
        $this->instance->send_midtrial_email_at = now()->subHour();
        $this->instance->trial_ends_at = now()->addDays(3);
        $this->instance->midtrial_email_sent = false;
        $this->instance->save();
    }

    public function test_midtrial_email_is_sent_and_flag_is_set(): void
    {
        Mail::fake();

        (new ProcessMidtrialEmailJob($this->instance->id))->handle();

        Mail::assertSent(AppInstanceMidtrialMail::class);

        $this->assertTrue($this->instance->fresh()->midtrial_email_sent);
    }

    public function test_flag_stays_false_when_send_throws(): void
    {
        // Force the synchronous send to fail so the job should throw before
        // the sent flag is updated.
        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('cc')->andReturnSelf();
        $pendingMail->shouldReceive('send')
            ->andThrow(new RuntimeException('SMTP failure'));

        Mail::shouldReceive('to')->andReturn($pendingMail);

        try {
            (new ProcessMidtrialEmailJob($this->instance->id))->handle();
            $this->fail('Expected the job to propagate the send exception.');
        } catch (RuntimeException $e) {
            $this->assertSame('SMTP failure', $e->getMessage());
        }

        $this->assertFalse(
            $this->instance->fresh()->midtrial_email_sent,
            'The sent flag must remain false when delivery fails so the job can retry.'
        );
    }
}

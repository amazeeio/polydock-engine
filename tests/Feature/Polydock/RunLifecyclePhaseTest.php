<?php

declare(strict_types=1);

namespace Tests\Feature\Polydock;

use App\Polydock\Apps\Generic\PolydockApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use Tests\Doubles\DoublePolydockAppInstance;
use Tests\TestCase;

class LifecyclePhaseTestApp extends PolydockApp
{
    public function validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus,
        $logContext = [],
        bool $testLagoonPing = true,
        bool $verifyLagoonValuesAreAvailable = true,
        bool $verifyLagoonProjectNameIsAvailable = true,
        bool $verifyLagoonProjectIdIsAvailable = true
    ): void {
        // No-op: the template's status handling is under test, not validation.
    }

    public function runPhase(PolydockAppInstanceInterface $appInstance, callable $body): PolydockAppInstanceInterface
    {
        return $this->runLifecyclePhase(
            $appInstance,
            'testPhase',
            PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
            PolydockAppInstanceStatus::PRE_REMOVE_RUNNING,
            PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
            PolydockAppInstanceStatus::PRE_REMOVE_FAILED,
            $body,
            'Phase completed',
        );
    }
}

class StatusRecordingAppInstance extends DoublePolydockAppInstance
{
    /** @var array<int, array{PolydockAppInstanceStatus, string}> */
    public array $statusCalls = [];

    public function setStatus(PolydockAppInstanceStatus $status, string $statusMessage = ''): self
    {
        $this->statusCalls[] = [$status, $statusMessage];

        return $this;
    }
}

/**
 * Pins the three exit paths of PolydockApp::runLifecyclePhase(): body returns
 * null (template marks the stage completed), body throws (template maps the
 * exception to the failed status), and body returns the instance (template
 * touches nothing after the running status).
 */
class RunLifecyclePhaseTest extends TestCase
{
    private function app(): LifecyclePhaseTestApp
    {
        return new LifecyclePhaseTestApp('Test App', 'desc', 'author', 'https://example.com', 'support@example.com');
    }

    public function test_a_null_returning_body_completes_the_stage(): void
    {
        $instance = new StatusRecordingAppInstance;
        $bodyArgs = null;

        $returned = $this->app()->runPhase($instance, function (PolydockAppInstanceInterface $appInstance, array $logContext) use (&$bodyArgs) {
            $bodyArgs = [$appInstance, $logContext];

            return null;
        });

        $this->assertSame($instance, $returned);
        $this->assertSame([$instance, ['class' => PolydockApp::class, 'location' => 'testPhase']], $bodyArgs);
        $this->assertSame([
            [PolydockAppInstanceStatus::PRE_REMOVE_RUNNING, PolydockAppInstanceStatus::PRE_REMOVE_RUNNING->getStatusMessage()],
            [PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED, 'Phase completed'],
        ], $instance->statusCalls);
    }

    public function test_a_throwing_body_fails_the_stage_with_the_exception_message(): void
    {
        $instance = new StatusRecordingAppInstance;

        $returned = $this->app()->runPhase($instance, function (): ?PolydockAppInstanceInterface {
            throw new \Exception('boom');
        });

        $this->assertSame($instance, $returned);
        $this->assertSame([
            [PolydockAppInstanceStatus::PRE_REMOVE_RUNNING, PolydockAppInstanceStatus::PRE_REMOVE_RUNNING->getStatusMessage()],
            [PolydockAppInstanceStatus::PRE_REMOVE_FAILED, 'An exception occurred: boom'],
        ], $instance->statusCalls);
    }

    public function test_a_short_circuiting_body_leaves_the_status_untouched(): void
    {
        $instance = new StatusRecordingAppInstance;

        $returned = $this->app()->runPhase($instance, function (PolydockAppInstanceInterface $appInstance) {
            return $appInstance;
        });

        $this->assertSame($instance, $returned);
        $this->assertSame([
            [PolydockAppInstanceStatus::PRE_REMOVE_RUNNING, PolydockAppInstanceStatus::PRE_REMOVE_RUNNING->getStatusMessage()],
        ], $instance->statusCalls);
    }
}
